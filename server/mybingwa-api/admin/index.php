<?php
require __DIR__ . '/lib_admin.php';
require_login();

$pdo = admin_db();

// Make the admin self-contained: create the tables if they don't exist yet, so
// the only setup step is uploading files (no manual SQL import required).
$pdo->exec("CREATE TABLE IF NOT EXISTS settings (
    skey VARCHAR(48) PRIMARY KEY, svalue VARCHAR(191) NOT NULL, updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS offers (
    offer_id VARCHAR(32) PRIMARY KEY, category VARCHAR(16) NOT NULL, name VARCHAR(64) NOT NULL,
    price INT NOT NULL, validity VARCHAR(32) NOT NULL, band VARCHAR(16) NOT NULL,
    daily_rule VARCHAR(20) NOT NULL DEFAULT 'BUY_AGAIN_TODAY', active TINYINT NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS templates (
    id INT AUTO_INCREMENT PRIMARY KEY, tkey VARCHAR(48) NOT NULL, label VARCHAR(80) NOT NULL,
    ttype VARCHAR(16) NOT NULL DEFAULT 'delivery', sender_id VARCHAR(32) NOT NULL DEFAULT '',
    category VARCHAR(16) NOT NULL DEFAULT 'DATA',
    pattern VARCHAR(255) NOT NULL, active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
// Upgrade an older templates table (created before type/sender/category existed).
foreach ([
    "ALTER TABLE templates ADD COLUMN ttype VARCHAR(16) NOT NULL DEFAULT 'delivery'",
    "ALTER TABLE templates ADD COLUMN sender_id VARCHAR(32) NOT NULL DEFAULT ''",
    "ALTER TABLE templates ADD COLUMN category VARCHAR(16) NOT NULL DEFAULT 'DATA'",
] as $alter) {
    try { $pdo->exec($alter); } catch (Throwable $e) { /* column already exists */ }
}

// ---- Handle actions (POST → redirect, so a refresh doesn't resubmit) ----------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $keys = ['till_number', 'paybill_number', 'support_number', 'support_whatsapp'];
        $stmt = $pdo->prepare(
            'INSERT INTO settings (skey, svalue, updated_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE svalue = VALUES(svalue), updated_at = NOW()'
        );
        foreach ($keys as $k) {
            $stmt->execute([$k, trim((string) ($_POST[$k] ?? ''))]);
        }
        set_flash('Payment & support details saved.');
    } elseif ($action === 'save_offer') {
        $stmt = $pdo->prepare(
            'INSERT INTO offers (offer_id, category, name, price, validity, band, daily_rule, active, sort_order)
             VALUES (:id, :cat, :name, :price, :validity, :band, :rule, :active, :sort)
             ON DUPLICATE KEY UPDATE category=:cat, name=:name, price=:price, validity=:validity,
                band=:band, daily_rule=:rule, active=:active, sort_order=:sort'
        );
        $stmt->execute([
            ':id' => trim((string) $_POST['offer_id']),
            ':cat' => $_POST['category'] ?? 'DATA',
            ':name' => trim((string) $_POST['name']),
            ':price' => (int) $_POST['price'],
            ':validity' => trim((string) $_POST['validity']),
            ':band' => $_POST['band'] ?? 'Daily',
            ':rule' => $_POST['daily_rule'] ?? 'BUY_AGAIN_TODAY',
            ':active' => isset($_POST['active']) ? 1 : 0,
            ':sort' => (int) ($_POST['sort_order'] ?? 0),
        ]);
        set_flash('Offer saved.');
    } elseif ($action === 'delete_offer') {
        $pdo->prepare('DELETE FROM offers WHERE offer_id = ?')->execute([$_POST['offer_id']]);
        set_flash('Offer deleted.');
    } elseif ($action === 'save_template') {
        $vals = [
            $_POST['tkey'] ?? '', $_POST['label'] ?? '', $_POST['ttype'] ?? 'delivery',
            $_POST['sender_id'] ?? '', $_POST['category'] ?? 'DATA', $_POST['pattern'] ?? '',
            isset($_POST['active']) ? 1 : 0,
        ];
        if (!empty($_POST['id'])) {
            $pdo->prepare('UPDATE templates SET tkey=?, label=?, ttype=?, sender_id=?, category=?, pattern=?, active=? WHERE id=?')
                ->execute(array_merge($vals, [(int) $_POST['id']]));
        } else {
            $pdo->prepare('INSERT INTO templates (tkey, label, ttype, sender_id, category, pattern, active) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute($vals);
        }
        set_flash('Template saved.');
    } elseif ($action === 'delete_template') {
        $pdo->prepare('DELETE FROM templates WHERE id = ?')->execute([(int) $_POST['id']]);
        set_flash('Template deleted.');
    } elseif ($action === 'load_defaults') {
        // Upsert the exact data the app ships with (offers, settings, templates).
        $seed = require __DIR__ . '/seed_data.php';

        $s = $pdo->prepare('INSERT INTO settings (skey, svalue, updated_at) VALUES (?, ?, NOW())
                            ON DUPLICATE KEY UPDATE svalue=VALUES(svalue), updated_at=NOW()');
        foreach ($seed['settings'] as $k => $v) {
            $s->execute([$k, $v]);
        }

        $o = $pdo->prepare('INSERT INTO offers (offer_id, category, name, price, validity, band, daily_rule, active, sort_order)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
                            ON DUPLICATE KEY UPDATE category=VALUES(category), name=VALUES(name), price=VALUES(price),
                                validity=VALUES(validity), band=VALUES(band), daily_rule=VALUES(daily_rule),
                                active=1, sort_order=VALUES(sort_order)');
        foreach ($seed['offers'] as $row) {
            [$id, $cat, $name, $price, $validity, $band, $once, $sort] = $row;
            $o->execute([$id, $cat, $name, $price, $validity, $band, $once ? 'ONCE_PER_DAY' : 'BUY_AGAIN_TODAY', $sort]);
        }

        // Replace templates with the app's canonical set.
        $pdo->exec('DELETE FROM templates');
        $t = $pdo->prepare('INSERT INTO templates (tkey, label, ttype, sender_id, category, pattern, active)
                            VALUES (?, ?, ?, ?, ?, ?, 1)');
        foreach ($seed['templates'] as $row) {
            [$tkey, $type, $sender, $cat, $pattern, $label] = $row;
            $t->execute([$tkey, $label, $type, $sender, $cat, $pattern]);
        }

        set_flash('App defaults loaded: ' . count($seed['offers']) . ' offers, ' .
            count($seed['settings']) . ' settings, ' . count($seed['templates']) . ' templates.');
    }

    header('Location: index.php');
    exit;
}

// ---- Load data for display ----------------------------------------------------
$settings = [];
foreach ($pdo->query('SELECT skey, svalue FROM settings') as $r) {
    $settings[$r['skey']] = $r['svalue'];
}
$offers = $pdo->query('SELECT * FROM offers ORDER BY sort_order, category, price')->fetchAll();
$templates = $pdo->query('SELECT * FROM templates ORDER BY tkey')->fetchAll();

$editOffer = null;
if (!empty($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM offers WHERE offer_id = ?');
    $s->execute([$_GET['edit']]);
    $editOffer = $s->fetch() ?: null;
}

$flash = take_flash();
$cats = ['DATA', 'SMS', 'MINUTES', 'SPECIAL'];
$bands = ['Hourly', 'Daily', 'Weekly', 'Monthly'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Bingwa Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="topbar">
    <div class="brand">My <b>Bingwa</b> · Admin</div>
    <a href="logout.php">Sign out</a>
</div>
<div class="wrap">
    <?php if ($flash): ?><div class="flash"><?= e($flash) ?></div><?php endif; ?>

    <!-- Load app defaults -->
    <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
        <div>
            <h2 style="margin:0">Sync app data</h2>
            <p class="sub" style="margin:2px 0 0">Fill this server with the exact offers, contact details and notification templates the app ships with. Safe to re-run; it updates matching rows.</p>
        </div>
        <form method="post" onsubmit="return confirm('Load the app defaults into the database? Existing offers/settings are updated; templates are replaced.')">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="load_defaults">
            <button class="btn">Load app defaults</button>
        </form>
    </div>

    <!-- Payment & support -->
    <div class="card">
        <h2>Payment &amp; support details</h2>
        <p class="sub">The app syncs these when online and caches them for offline use.</p>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_settings">
            <div class="row">
                <div>
                    <label>Till number</label>
                    <input name="till_number" value="<?= e($settings['till_number'] ?? '4953696') ?>">
                </div>
                <div>
                    <label>Paybill number</label>
                    <input name="paybill_number" value="<?= e($settings['paybill_number'] ?? '40450595') ?>">
                </div>
            </div>
            <div class="row">
                <div>
                    <label>Support number</label>
                    <input name="support_number" value="<?= e($settings['support_number'] ?? '0727921038') ?>">
                </div>
                <div>
                    <label>Support WhatsApp (2547…)</label>
                    <input name="support_whatsapp" value="<?= e($settings['support_whatsapp'] ?? '254727921038') ?>">
                </div>
            </div>
            <div style="height:14px"></div>
            <button class="btn">Save details</button>
        </form>
    </div>

    <!-- Offers -->
    <div class="card">
        <h2>Offers</h2>
        <p class="sub">The catalogue the app fetches. Toggle Active to hide an offer without deleting it.</p>
        <table>
            <tr><th>ID</th><th>Category</th><th>Name</th><th>Price</th><th>Validity</th><th>Rule</th><th>Status</th><th></th></tr>
            <?php foreach ($offers as $o): ?>
            <tr>
                <td class="muted"><?= e($o['offer_id']) ?></td>
                <td><span class="tag <?= strtolower(e($o['category'])) ?>"><?= e($o['category']) ?></span></td>
                <td><?= e($o['name']) ?></td>
                <td>KSh <?= e($o['price']) ?></td>
                <td><?= e($o['validity']) ?></td>
                <td class="muted"><?= $o['daily_rule'] === 'ONCE_PER_DAY' ? 'Once/day' : 'Repeatable' ?></td>
                <td><?php if ($o['active']): ?><span class="tag sms">Active</span><?php else: ?><span class="tag off">Hidden</span><?php endif; ?></td>
                <td style="white-space:nowrap">
                    <a class="btn small secondary" href="?edit=<?= e($o['offer_id']) ?>#offer-form">Edit</a>
                    <form class="inline-form" method="post" onsubmit="return confirm('Delete this offer?')">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete_offer">
                        <input type="hidden" name="offer_id" value="<?= e($o['offer_id']) ?>">
                        <button class="btn danger">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$offers): ?><tr><td colspan="8" class="muted">No offers yet — add one below.</td></tr><?php endif; ?>
        </table>

        <h2 id="offer-form" style="margin-top:22px"><?= $editOffer ? 'Edit offer' : 'Add offer' ?></h2>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_offer">
            <div class="row">
                <div>
                    <label>Offer ID</label>
                    <input name="offer_id" value="<?= e($editOffer['offer_id'] ?? '') ?>" <?= $editOffer ? 'readonly' : '' ?> placeholder="data_14" required>
                </div>
                <div>
                    <label>Category</label>
                    <select name="category">
                        <?php foreach ($cats as $c): ?>
                        <option value="<?= $c ?>" <?= (($editOffer['category'] ?? '') === $c) ? 'selected' : '' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <div>
                    <label>Name (allowance)</label>
                    <input name="name" value="<?= e($editOffer['name'] ?? '') ?>" placeholder="2GB" required>
                </div>
                <div>
                    <label>Price (KSh)</label>
                    <input name="price" type="number" value="<?= e($editOffer['price'] ?? '') ?>" required>
                </div>
            </div>
            <div class="row">
                <div>
                    <label>Validity</label>
                    <input name="validity" value="<?= e($editOffer['validity'] ?? '') ?>" placeholder="24 Hrs" required>
                </div>
                <div>
                    <label>Validity band</label>
                    <select name="band">
                        <?php foreach ($bands as $b): ?>
                        <option value="<?= $b ?>" <?= (($editOffer['band'] ?? '') === $b) ? 'selected' : '' ?>><?= $b ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Daily rule</label>
                    <select name="daily_rule">
                        <option value="BUY_AGAIN_TODAY" <?= (($editOffer['daily_rule'] ?? '') === 'BUY_AGAIN_TODAY') ? 'selected' : '' ?>>Repeatable</option>
                        <option value="ONCE_PER_DAY" <?= (($editOffer['daily_rule'] ?? '') === 'ONCE_PER_DAY') ? 'selected' : '' ?>>Once per day</option>
                    </select>
                </div>
                <div>
                    <label>Sort</label>
                    <input name="sort_order" type="number" value="<?= e($editOffer['sort_order'] ?? 0) ?>">
                </div>
            </div>
            <label style="display:flex;align-items:center;gap:8px;margin-top:14px">
                <input type="checkbox" name="active" style="width:auto" <?= (!$editOffer || $editOffer['active']) ? 'checked' : '' ?>> Active (visible in the app)
            </label>
            <div style="height:14px"></div>
            <button class="btn"><?= $editOffer ? 'Update offer' : 'Add offer' ?></button>
            <?php if ($editOffer): ?><a class="btn secondary" href="index.php">Cancel</a><?php endif; ?>
        </form>
    </div>

    <!-- Notification templates -->
    <div class="card">
        <h2>Notification templates</h2>
        <p class="sub">Delivery / low-balance SMS patterns the app uses to recognise Safaricom messages. Advanced — leave as-is unless Safaricom changes its wording.</p>
        <table>
            <tr><th>Key</th><th>Type</th><th>Sender</th><th>Category</th><th>Pattern</th><th>Status</th><th></th></tr>
            <?php foreach ($templates as $t): ?>
            <tr>
                <td class="muted"><?= e($t['tkey']) ?><br><span class="muted"><?= e($t['label']) ?></span></td>
                <td><?= ($t['ttype'] ?? 'delivery') === 'low_balance' ? 'Low balance' : 'Delivery' ?></td>
                <td class="muted"><?= e($t['sender_id'] ?? '') ?></td>
                <td><span class="tag <?= strtolower(e($t['category'] ?? 'data')) ?>"><?= e($t['category'] ?? 'DATA') ?></span></td>
                <td class="muted" style="font-family:monospace;font-size:12px"><?= e($t['pattern']) ?></td>
                <td><?php if ($t['active']): ?><span class="tag sms">On</span><?php else: ?><span class="tag off">Off</span><?php endif; ?></td>
                <td>
                    <form class="inline-form" method="post" onsubmit="return confirm('Delete this template?')">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete_template">
                        <input type="hidden" name="id" value="<?= e($t['id']) ?>">
                        <button class="btn danger">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$templates): ?><tr><td colspan="7" class="muted">No templates yet — click "Load app defaults" above.</td></tr><?php endif; ?>
        </table>

        <h2 style="margin-top:22px">Add template</h2>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_template">
            <div class="row">
                <div>
                    <label>Key</label>
                    <input name="tkey" placeholder="data_bingwa_sokoni" required>
                </div>
                <div>
                    <label>Label</label>
                    <input name="label" placeholder="Data delivery" required>
                </div>
                <div>
                    <label>Type</label>
                    <select name="ttype">
                        <option value="delivery">Delivery</option>
                        <option value="low_balance">Low balance</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div>
                    <label>Sender ID</label>
                    <input name="sender_id" placeholder="Safaricom">
                </div>
                <div>
                    <label>Category</label>
                    <select name="category">
                        <?php foreach ($cats as $c): ?><option value="<?= $c ?>"><?= $c ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <label>Pattern (regex, case-insensitive)</label>
            <input name="pattern" placeholder="received\s+\d+\s+SMS" required>
            <label style="display:flex;align-items:center;gap:8px;margin-top:14px">
                <input type="checkbox" name="active" style="width:auto" checked> Active
            </label>
            <div style="height:14px"></div>
            <button class="btn">Add template</button>
        </form>
    </div>

    <p class="muted" style="text-align:center">My Bingwa admin · changes go live on the app's next online sync.</p>
</div>
</body>
</html>
