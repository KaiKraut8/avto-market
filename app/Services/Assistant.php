<?php

namespace App\Services;

use App\Models\Car;
use App\Support\CarSearch;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

// Answers visitors' questions about the site and the cars: a model on an Ollama server (with tools to search the cars),
// Claude, or a small built-in helper that recognises the common questions and searches the cars (config/assistant.php).
class Assistant
{
    public function __construct(private CarCatalog $catalog) {}

    public function driver(): string
    {
        $driver = config('assistant.driver');
        if ($driver !== 'auto') {
            return $driver;
        }

        return config('assistant.ollama.url') ? 'ollama' : (config('assistant.key') ? 'anthropic' : 'builtin');
    }

    /** @param array<int, array{role: string, content: string}> $messages  @return array{reply: string, links: array} */
    public function reply(array $messages): array
    {
        $messages = $this->trim($messages);
        if ($messages === []) {
            return ['reply' => __('Hello! Ask me about a car, prices, premium or how buying works.'), 'links' => []];
        }
        try {
            $answer = match ($this->driver()) {
                'ollama' => $this->askOllama($messages),
                'anthropic' => ['reply' => $this->askClaude($messages), 'links' => []],
                default => null,
            };
            if ($answer && $answer['reply'] !== '') {
                return $answer;
            }
        } catch (\Throwable $e) {
            report($e);   // too slow or unreachable: fall through to the built-in answers
        }

        return $this->builtIn(end($messages)['content']);
    }

    // Only the last turns, user/assistant alternating, each capped in length
    private function trim(array $messages): array
    {
        $clean = [];
        foreach (array_slice($messages, -(int) config('assistant.history')) as $m) {
            $role = ($m['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim(mb_substr((string) ($m['content'] ?? ''), 0, (int) config('assistant.max_message')));
            if ($content === '' || (end($clean) && end($clean)['role'] === $role)) {
                continue;
            }
            $clean[] = ['role' => $role, 'content' => $content];
        }
        while ($clean && $clean[0]['role'] !== 'user') {
            array_shift($clean);
        }

        return $clean;
    }

    private function askClaude(array $messages): string
    {
        $response = Http::withHeaders(['x-api-key' => config('assistant.key'), 'anthropic-version' => '2023-06-01'])
            ->acceptJson()->asJson()->timeout(30)->throw()
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => config('assistant.model'),
                'max_tokens' => (int) config('assistant.max_tokens'),
                'system' => $this->systemPrompt(),
                'messages' => $messages,
            ]);

        return trim(collect($response->json('content'))->where('type', 'text')->pluck('text')->implode("\n"));
    }

    // Ollama's chat API with tool calling: the model may search the cars (up to a few rounds) before it answers.
    // The links under the answer are the cars the tools found.
    private function askOllama(array $messages): array
    {
        $cfg = config('assistant.ollama');
        $deadline = microtime(true) + $cfg['timeout'];
        $conversation = [['role' => 'system', 'content' => $this->ollamaPrompt()], ...$messages];
        $found = [];

        for ($round = 0; $round <= $cfg['max_tool_rounds']; $round++) {
            $left = $deadline - microtime(true);
            if ($left < 1) {
                throw new \RuntimeException('The assistant model took longer than '.$cfg['timeout'].' s.');
            }
            $message = Http::acceptJson()->asJson()->timeout((int) ceil($left))->throw()
                ->post(rtrim($cfg['url'], '/').'/api/chat', [
                    'model' => $cfg['model'],
                    'messages' => $conversation,
                    'tools' => $round < $cfg['max_tool_rounds'] ? self::ollamaTools() : [],
                    'stream' => false,
                    // no "think" option: with think=false this Qwen3 build writes its reasoning into the answer;
                    // left out, the reasoning comes in a separate "thinking" field that is ignored here
                    'keep_alive' => $cfg['keep_alive'],
                ])->json('message');

            $calls = $message['tool_calls'] ?? [];
            if (! $calls) {
                $reply = trim(preg_replace('/<think>.*?<\/think>/s', '', (string) ($message['content'] ?? '')));

                return ['reply' => $reply, 'links' => array_slice(array_values($found), 0, 3)];
            }
            $conversation[] = $message;
            foreach ($calls as $call) {
                $name = $call['function']['name'] ?? '';
                $result = $this->runTool($name, (array) ($call['function']['arguments'] ?? []));
                foreach ($result['cars'] ?? (isset($result['id']) ? [$result] : []) as $car) {
                    $found[$car['id']] = [$car['name'].($car['year'] ? " ({$car['year']})" : '').' · '.($car['price_eur'] !== null ? Money::price($car['deal']['price_eur'] ?? $car['price_eur']) : __('Price on request')), $car['url']];
                }
                $conversation[] = ['role' => 'tool', 'tool_name' => $name, 'content' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
            }
        }

        throw new \RuntimeException('The assistant model kept calling tools.');
    }

    private function runTool(string $name, array $args): array
    {
        return match ($name) {
            'search_cars' => $this->catalog->search($args, (int) ($args['limit'] ?? 5)),
            'get_car' => $this->catalog->car((int) ($args['id'] ?? 0)) ?? ['error' => 'No car with that id.'],
            'list_car_filters' => $this->catalog->options(),
            default => ['error' => "Unknown tool {$name}."],
        };
    }

    // The same tools as the MCP server (app/Mcp/Tools), in Ollama's function format
    public static function ollamaTools(): array
    {
        $fn = fn (string $name, string $description, array $properties, array $required = []) => ['type' => 'function', 'function' => [
            'name' => $name, 'description' => $description,
            'parameters' => ['type' => 'object', 'properties' => (object) $properties, 'required' => $required],
        ]];

        return [
            $fn('search_cars', 'Search the used cars for sale. All filters optional; returns the number of matches and the cars with id, name, year, price in EUR, deal, location and url.', [
                'query' => ['type' => 'string', 'description' => 'Free text: model or place, e.g. "Golf", "Ljubljana"'],
                'make' => ['type' => 'string', 'description' => 'Make, e.g. "audi", "bmw", "škoda"'],
                'year_from' => ['type' => 'integer'], 'year_to' => ['type' => 'integer'],
                'price_from' => ['type' => 'integer', 'description' => 'EUR'], 'price_to' => ['type' => 'integer', 'description' => 'EUR'],
                'country' => ['type' => 'string', 'description' => 'In English, e.g. "Slovenia"'],
                'deals_only' => ['type' => 'boolean', 'description' => 'Only cars with a price drop right now'],
                'sort' => ['type' => 'string', 'enum' => CarSearch::SORTS],
                'limit' => ['type' => 'integer', 'description' => '1-10, default 5'],
            ]),
            $fn('get_car', 'Full details of one car by id: description, photos, seller, deal and how to buy it.', ['id' => ['type' => 'integer']], ['id']),
            $fn('list_car_filters', 'Which makes, countries, years and prices the cars for sale have.', []),
        ];
    }

    // Kept short: a model on a modest server reads every word of it for every question
    public function ollamaPrompt(): string
    {
        $rate = (int) config('pricing.commission_rate');

        return "You are the assistant of KAI Garage, a used-car marketplace in Ljubljana, Slovenia ({$this->base()}). "
            .'Reply in the visitor\'s language, in 1-3 short sentences, plain text. Only help with KAI Garage. '
            .'Use search_cars to find cars and never invent cars or prices; mention the cars by name (their links are shown under your answer). '
            ."Buying: the buyer pays {$rate}% online with the button \"".__('Buy this car')."\" to reserve it and the rest to the seller at the handover ({$this->base()}/how-buying-works). "
            .'Listing a car is free. Premium seller from '.Money::eur(Pricing::yearly() / 12).' a month, premium buyer from '.Money::eur(Pricing::buyerYearly() / 12)." a month ({$this->base()}/premium). "
            .'Contact: '.config('company.phone').', '.config('company.email').'.';
    }

    // What Claude is told about the site: the rules, the prices and the cars for sale right now
    public function systemPrompt(): string
    {
        $rate = (int) config('pricing.commission_rate');
        $cars = Car::query()->forSale()->with('activeDeal')->orderBy('id')->limit(60)->get()
            ->map(fn (Car $c) => sprintf('- #%d %s | %s | %s | %s | %s', $c->id, $c->name, $c->year ?: '?',
                $c->price !== null ? Money::price($c->activeDeal ? $c->activeDeal->deal_price : $c->price) : 'price on request',
                $c->locationLabel() ?: '?', url(route('cars.show', $c, false))))
            ->implode("\n");

        return <<<TXT
You are the assistant of KAI Garage, a used-car marketplace at {$this->base()}. Answer in the language the visitor writes in (the site is in English, Slovenian, Croatian, German, Dutch, French, Spanish and Portuguese). Be brief and friendly: a few sentences, plain text, no markdown headings. Only talk about the site and its cars; for anything else say politely that you can only help with KAI Garage. Never invent cars, prices or rules that aren't listed here. When you mention a car or a page, give its link on its own line.

Company: {config('company.name')}, {config('company.address')}, phone {config('company.phone')}, email {config('company.email')}, hours {config('company.hours')}.

How buying works ({$this->base()}/how-buying-works): the buyer presses "Buy this car" and pays {$rate}% of the price online (card, PayPal or paysafecard; paysafecard only up to 1.000 €). That reserves the car and gives buyer and seller each other's contact details. At the handover the buyer pays the seller the remaining {$this->pct(100 - $rate)}%, so the buyer pays exactly the listed price and the seller receives {$this->pct(100 - $rate)}% of it; KAI Garage keeps {$rate}%. The seller then confirms the sale or cancels it (the buyer is refunded in full). A car sold elsewhere must be marked sold by the seller, who pays the {$rate}% commission; until then they can't list new cars. "Contact seller" is for questions; the purchase always goes through "Buy this car".

Listing a car is free and needs an account ({$this->base()}/register). Search: {$this->base()}/cars with text, year and price filters (?q=, ?year_from=, ?year_to=, ?price_from=, ?price_to=). Wishlist works without an account. Deals: {$this->base()}/deals.

Premium ({$this->base()}/premium), prices per month: Premium seller {$this->eur(Pricing::monthly())} monthly, {$this->eur(Pricing::price('seller', 'quarterly') / 3)} on the 3-month plan ({$this->eur(Pricing::price('seller', 'quarterly'))} per 3 months, {$this->pct(Pricing::quarterlySaving())}% off), {$this->eur(Pricing::yearly() / 12)} on the yearly plan ({$this->eur(Pricing::yearly())} per year, {$this->pct(Pricing::yearlySaving())}% off): top placement in gold, special deals, member prices, insights, alerts. Premium buyer {$this->eur(Pricing::buyerMonthly())} monthly, {$this->eur(Pricing::price('buyer', 'quarterly'))} per 3 months, {$this->eur(Pricing::buyerYearly())} per year: member prices on deals, alerts for deals on similar cars, up to 10 saved searches. Push forward: {$this->eur(Pricing::boostWeekly())} per week per car. Subscriptions renew until cancelled on the account page.

Cars for sale right now (id, name, year, price, location, link):
{$cars}
TXT;
    }

    private function base(): string
    {
        return rtrim(config('app.url'), '/');
    }

    private function eur(float $v): string
    {
        return Money::eur($v);
    }

    private function pct(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }

    // Without an API key: common questions by keyword, and a car search for everything else
    private function builtIn(string $text): array
    {
        $t = Str::lower(Str::ascii($text));
        $has = fn (string ...$words) => (bool) array_filter($words, fn ($w) => str_contains($t, $w));

        if ($has('hello', 'hi ', 'hey', 'zivjo', 'zdravo', 'dober dan', 'hallo', 'bonjour', 'hola', 'ola', 'bok') && mb_strlen($t) < 25) {
            return ['reply' => __('Hello! Ask me about a car, prices, premium or how buying works.'), 'links' => []];
        }
        if ($has('commission', 'provizij', 'how buy', 'kako kup', 'buying work', 'nakup', 'reserv', 'rezerv', 'handover', 'predaj', 'kaufen', 'acheter', 'comprar', 'kupnj', 'kopen')) {
            return ['reply' => __('Every car is bought through the site: you pay :rate% of the price online, which reserves the car, and the rest to the seller at the handover. You pay exactly the listed price. If the seller cancels, you get your :rate% back.', ['rate' => (int) config('pricing.commission_rate')]),
                'links' => [[__('How buying works'), route('how-buying')]]];
        }
        if ($has('premium', 'subscri', 'narocn', 'abo', 'abonn', 'suscri', 'assin', 'pretplat')) {
            return ['reply' => __('Premium seller puts every car you list at the top, in gold, with special deals and insights, from :seller a month. Premium buyer gets member prices and alerts for deals, from :buyer a month. The 3-month and yearly plans are cheaper per month.',
                ['seller' => Money::eur(Pricing::yearly() / 12), 'buyer' => Money::eur(Pricing::buyerYearly() / 12)]),
                'links' => [[__('Premium'), route('premium.index')]]];
        }
        if ($has('sell', 'prodaj', 'prodat', 'list a car', 'objav', 'verkauf', 'vendre', 'vender', 'verkop', 'add car', 'dodaj')) {
            return ['reply' => __('Listing a car is free: create an account, press "Add car", add photos and the price. When it sells through the site, :rate% of the price goes to KAI Garage.', ['rate' => (int) config('pricing.commission_rate')]),
                'links' => [[__('Add car'), route('cars.create')], [__('How buying works'), route('how-buying')]]];
        }
        if ($has('contact', 'kontakt', 'phone', 'telefon', 'email', 'e-mail', 'address', 'naslov', 'hours', 'odprt', 'open', 'where', 'kje', 'adresse', 'horaire', 'direccion', 'morada')) {
            return ['reply' => __(':company, :address. Phone :phone, email :email. Open :hours.', ['company' => config('company.name'), 'address' => config('company.address'), 'phone' => config('company.phone'), 'email' => config('company.email'), 'hours' => config('company.hours')]),
                'links' => [[__('Contact'), route('contact')]]];
        }
        if ($has('deal', 'akcij', 'discount', 'popust', 'rabatt', 'promo', 'oferta', 'offre', 'korting', 'cheap', 'poceni')) {
            $n = Car::query()->forSale()->whereHas('deals', fn ($q) => $q->where('ends_at', '>', now()))->count();

            return ['reply' => trans_choice('There is :count special deal running right now.|There are :count special deals running right now.', $n), 'links' => [[__('Deals'), route('deals.index')]]];
        }

        // anything else: look for cars matching the words, a year or a price in the question
        $search = $this->searchFrom($t);
        // nothing recognisable (no make, model, year or price): don't list random cars
        if ($search->isEmpty()) {
            return preg_match('/[a-z]{4,}/', $t)
                ? ['reply' => __("I couldn't find a car like that. Try a make, a year or a price, e.g. \"BMW up to 30.000 €\"."), 'links' => [[__('All cars'), route('cars.index')]]]
                : ['reply' => __('I can help with the cars for sale, prices, premium, selling and how buying works. What would you like to know?'), 'links' => [[__('All cars'), route('cars.index')]]];
        }
        $cars = Car::query()->forSale()->with('activeDeal')->tap(fn ($q) => $search->apply($q))->limit(3)->get();
        if ($cars->isEmpty()) {
            return ['reply' => __("I couldn't find a car like that. Try a make, a year or a price, e.g. \"BMW up to 30.000 €\"."), 'links' => [[__('All cars'), route('cars.index')]]];
        }

        return [
            'reply' => trans_choice('Here is :count car that may fit:|Here are :count cars that may fit:', $cars->count()),
            'links' => $cars->map(fn (Car $c) => [$c->name.($c->year ? " ({$c->year})" : '').' · '.Money::price($c->activeDeal ? $c->activeDeal->deal_price : $c->price), route('cars.show', $c)])->all(),
        ];
    }

    // "audi 2019 under 25000" → text "audi", year 2019, price up to 25000
    private function searchFrom(string $t): CarSearch
    {
        $yearFrom = $yearTo = $priceFrom = $priceTo = null;
        if (preg_match_all('/\b(19[5-9]\d|20\d\d)\b/', $t, $m)) {
            $years = array_map('intval', $m[1]);
            $yearFrom = min($years);
            $yearTo = count($years) > 1 ? max($years) : null;
            if (count($years) === 1 && preg_match('/\b(from|newer|after|od|ab|nach|depuis|desde)\b/', $t)) {
                $yearTo = null;
            } elseif (count($years) === 1 && preg_match('/\b(to|until|up to|older|before|do|bis|vor|avant|hasta|ate)\b/', $t)) {
                [$yearFrom, $yearTo] = [null, $yearFrom];
            } elseif (count($years) === 1) {
                $yearTo = $yearFrom;
            }
        }
        $words = preg_replace('/\b(19[5-9]\d|20\d\d)\b/', ' ', $t);
        if (preg_match_all('/(\d[\d.,]{2,})\s*(?:€|eur|k\b)?/', $words, $m)) {
            $amounts = array_map(fn ($a) => (int) Money::parse($a), $m[1]);
            $amounts = array_values(array_filter($amounts, fn ($a) => $a >= 100));
            if (count($amounts) >= 2) {
                [$priceFrom, $priceTo] = [min($amounts), max($amounts)];
            } elseif (count($amounts) === 1) {
                preg_match('/\b(over|above|from|more|od|nad|ab|uber|plus de|mas de|mais de)\b/', $words) ? $priceFrom = $amounts[0] : $priceTo = $amounts[0];
            }
            $words = preg_replace('/\d[\d.,]{2,}\s*(?:€|eur|k\b)?/', ' ', $words);
        }
        // a make among the words becomes the make filter; of the other words only those that appear in a car's
        // name are kept (a model like "golf" or "a4"), so filler words in any language don't spoil the search
        $tokens = preg_split('/[^a-z0-9]+/', $words, -1, PREG_SPLIT_NO_EMPTY);
        $makes = collect(array_keys(CarSearch::bounds()['makes']))->mapWithKeys(fn ($m) => [Str::lower(Str::ascii($m)) => $m]);
        $make = '';
        foreach ($tokens as $i => $token) {
            if ($makes->has($token)) {
                $make = $makes[$token];
                unset($tokens[$i]);
                break;
            }
        }
        $nameWords = Car::query()->forSale()->pluck('name')
            ->flatMap(fn ($n) => preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($n)), -1, PREG_SPLIT_NO_EMPTY))->flip();
        $q = implode(' ', array_filter($tokens, fn ($t) => mb_strlen($t) > 1 && isset($nameWords[$t])));

        return new CarSearch(q: mb_substr($q, 0, 80), yearFrom: $yearFrom, yearTo: $yearTo, priceFrom: $priceFrom, priceTo: $priceTo, make: $make);
    }
}
