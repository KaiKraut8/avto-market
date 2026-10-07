<?php
// Single-file CRUD for the cars table.
// The page talks to itself with fetch(), so add / edit / delete happen in the background (no reload).

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli("db", "app", "secret", "app");
$conn->set_charset("utf8mb4");

function respond(array $data, int $status = 200): never
{
    http_response_code($status);
    header("Content-Type: application/json");
    echo json_encode($data);
    exit;
}

if ($conn->connect_error) {
    respond(["error" => "Connection failed"], 500);
}

// ---- Schema: soft delete + change log (created once, safe to run every request) ----
$hasCol = $conn->query("SHOW COLUMNS FROM cars LIKE 'deleted_at'")->num_rows > 0;
if (!$hasCol) {
    $conn->query("ALTER TABLE cars ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
}
$conn->query("CREATE TABLE IF NOT EXISTS cars_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    car_id INT NOT NULL,
    action VARCHAR(10) NOT NULL,
    old_name VARCHAR(50) NULL,
    new_name VARCHAR(50) NULL,
    logged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)");

function logChange(mysqli $conn, int $carId, string $action, ?string $old, ?string $new): void
{
    $stmt = $conn->prepare("INSERT INTO cars_log (car_id, action, old_name, new_name) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $carId, $action, $old, $new);
    $stmt->execute();
}

// ---- API: ?action=list|get|add|update|delete|restore ----
$action = $_GET["action"] ?? null;
if ($action !== null) {
    $id = filter_var($_POST["id"] ?? $_GET["id"] ?? null, FILTER_VALIDATE_INT);
    $name = trim($_POST["name"] ?? "");

    switch ($action) {
        case "list":
            $where = isset($_GET["deleted"]) ? "deleted_at IS NOT NULL" : "deleted_at IS NULL";
            $rows = $conn->query("SELECT id, name, creation_date, deleted_at FROM cars WHERE $where ORDER BY id")->fetch_all(MYSQLI_ASSOC);
            respond(["cars" => $rows]);

        case "get": // only the one row that was clicked
            if ($id === false || $id === null) respond(["error" => "Bad id"], 400);
            $stmt = $conn->prepare("SELECT id, name, creation_date FROM cars WHERE id = ? AND deleted_at IS NULL");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $car = $stmt->get_result()->fetch_assoc();
            $car ? respond(["car" => $car]) : respond(["error" => "Not found"], 404);

        case "add":
            if ($name === "" || mb_strlen($name) > 50) respond(["error" => "Name is required (max 50 characters)"], 400);
            $stmt = $conn->prepare("INSERT INTO cars (name, creation_date) VALUES (?, NOW())");
            $stmt->bind_param("s", $name);
            if (!$stmt->execute()) respond(["error" => $stmt->error], 500);
            $newId = $conn->insert_id;
            logChange($conn, $newId, "add", null, $name);
            respond(["ok" => true, "id" => $newId]);

        case "update":
            if ($id === false || $id === null) respond(["error" => "Bad id"], 400);
            if ($name === "" || mb_strlen($name) > 50) respond(["error" => "Name is required (max 50 characters)"], 400);
            $old = $conn->prepare("SELECT name FROM cars WHERE id = ? AND deleted_at IS NULL");
            $old->bind_param("i", $id);
            $old->execute();
            $before = $old->get_result()->fetch_assoc();
            if (!$before) respond(["error" => "Not found"], 404);
            $stmt = $conn->prepare("UPDATE cars SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $name, $id);
            if (!$stmt->execute()) respond(["error" => $stmt->error], 500);
            logChange($conn, $id, "edit", $before["name"], $name);
            respond(["ok" => true]);

        case "delete":
            if ($id === false || $id === null) respond(["error" => "Bad id"], 400);
            // Soft delete: the row stays in the database, it is just hidden from the app
            $stmt = $conn->prepare("UPDATE cars SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
            $stmt->bind_param("i", $id);
            if (!$stmt->execute()) respond(["error" => $stmt->error], 500);
            if ($stmt->affected_rows) logChange($conn, $id, "delete", null, null);
            respond(["ok" => true]);

        case "restore":
            if ($id === false || $id === null) respond(["error" => "Bad id"], 400);
            $stmt = $conn->prepare("UPDATE cars SET deleted_at = NULL WHERE id = ?");
            $stmt->bind_param("i", $id);
            if (!$stmt->execute()) respond(["error" => $stmt->error], 500);
            logChange($conn, $id, "restore", null, null);
            respond(["ok" => true]);

        default:
            respond(["error" => "Unknown action"], 400);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cars</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 42rem; padding: 0 1rem; }
        table { border-collapse: collapse; width: 100%; margin: 1rem 0; }
        th, td { border: 1px solid #ccc; padding: 6px 10px; text-align: left; }
        th { background: #f3f3f3; }
        td.actions { white-space: nowrap; width: 1%; }
        #status { min-height: 1.5em; color: #060; }
        #status.error { color: #b00; }
        form { display: flex; gap: .5rem; flex-wrap: wrap; align-items: center; }
        input[type=text] { padding: 4px 8px; }
    </style>
</head>
<body>

<h1>Cars</h1>
<div id="status"></div>

<label><input type="checkbox" id="show-deleted"> Show deleted</label>

<table>
    <thead>
        <tr><th>ID</th><th>Name</th><th>Creation Date</th><th></th></tr>
    </thead>
    <tbody id="rows"></tbody>
</table>

<h2 id="form-title">Add car</h2>
<form id="car-form">
    <input type="hidden" id="car-id">
    <label for="car-name">Name:</label>
    <input type="text" id="car-name" maxlength="50" required>
    <button type="submit" id="save-btn">Add</button>
    <button type="button" id="cancel-btn" hidden>Cancel</button>
</form>

<script>
const $ = (id) => document.getElementById(id);
const rows = $("rows"), form = $("car-form"), status = $("status");

async function api(action, data) {
    const extra = action === "list" && $("show-deleted").checked ? "&deleted=1" : "";
    const res = await fetch("?action=" + action + extra + (data && !(data instanceof URLSearchParams) ? "&id=" + data : ""), {
        method: data instanceof URLSearchParams ? "POST" : "GET",
        body: data instanceof URLSearchParams ? data : undefined,
    });
    const json = await res.json();
    if (!res.ok) throw new Error(json.error || "Request failed");
    return json;
}

function say(msg, isError = false) {
    status.textContent = msg;
    status.className = isError ? "error" : "";
}

function cell(text) {
    const td = document.createElement("td");
    td.textContent = text; // textContent, so database values can't inject HTML
    return td;
}

function button(label, onClick) {
    const b = document.createElement("button");
    b.type = "button";
    b.textContent = label;
    b.addEventListener("click", onClick);
    return b;
}

async function load() {
    const { cars } = await api("list");
    rows.replaceChildren();
    if (cars.length === 0) {
        const tr = document.createElement("tr");
        const td = cell("0 results");
        td.colSpan = 4;
        tr.append(td);
        rows.append(tr);
        return;
    }
    for (const car of cars) {
        const tr = document.createElement("tr");
        const actions = document.createElement("td");
        actions.className = "actions";
        if (car.deleted_at) {
            actions.append(button("Restore", () => restore(car)));
        } else {
            actions.append(button("Edit", () => startEdit(car.id)), " ", button("Delete", () => remove(car)));
        }
        tr.append(cell(car.id), cell(car.name), cell(car.creation_date), actions);
        rows.append(tr);
    }
}

function resetForm() {
    form.reset();
    $("car-id").value = "";
    $("form-title").textContent = "Add car";
    $("save-btn").textContent = "Add";
    $("cancel-btn").hidden = true;
}

async function startEdit(id) {
    try {
        const { car } = await api("get", id); // fetches just this one row
        $("car-id").value = car.id;
        $("car-name").value = car.name;
        $("form-title").textContent = "Edit car #" + car.id;
        $("save-btn").textContent = "Save";
        $("cancel-btn").hidden = false;
        $("car-name").focus();
    } catch (e) { say(e.message, true); }
}

async function restore(car) {
    try {
        await api("restore", new URLSearchParams({ id: car.id }));
        say("Restored.");
        await load();
    } catch (e) { say(e.message, true); }
}

async function remove(car) {
    if (!confirm('Delete "' + car.name + '"? It stays in the database and can be restored.')) return;
    try {
        await api("delete", new URLSearchParams({ id: car.id }));
        if ($("car-id").value === String(car.id)) resetForm();
        say("Deleted.");
        await load();
    } catch (e) { say(e.message, true); }
}

form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const id = $("car-id").value;
    const body = new URLSearchParams({ name: $("car-name").value });
    if (id) body.set("id", id);
    try {
        await api(id ? "update" : "add", body);
        say(id ? "Saved." : "Added.");
        resetForm();
        await load();
    } catch (e) { say(e.message, true); }
});

$("cancel-btn").addEventListener("click", resetForm);
$("show-deleted").addEventListener("change", () => { resetForm(); load().catch((e) => say(e.message, true)); });

load().catch((e) => say(e.message, true));
</script>

</body>
</html>
