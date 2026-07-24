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
    pattern VARCHAR(255) NOT NULL, active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
        if (!empty($_POST['id'])) {
            $pdo->prepare('UPDATE templates SET tkey=?, label=?, pattern=?, active=? WHERE id=?')
                ->execute([$_POST['tkey'], $_POST['label'], $_POST['pattern'], isset($_POST['active']) ? 1 : 0, (int) $_POST['id']]);
        } else {
            $pdo->prepare('INSERT INTO templates (tkey, label, pattern, active) VALUES (?, ?, ?, ?)')
                ->execute([$_POST['tkey'], $_POST['label'], $_POST['pattern'], isset($_POST['active']) ? 1 : 0]);
        }
        set_flash('Template saved.');
    } elseif ($action === 'delete_template') {
        $pdo->prepare('DELETE FROM templates WHERE id = ?')->execute([(int) $_POST['id']]);
        set_flash('Template deleted.');
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
            <tr><th>Key</th><th>Label</th><th>Pattern</th><th>Status</th><th></th></tr>
            <?php foreach ($templates as $t): ?>
            <tr>
                <td class="muted"><?= e($t['tkey']) ?></td>
                <td><?= e($t['label']) ?></td>
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
            <?php if (!$templates): ?><tr><td colspan="5" class="muted">No templates yet.</td></tr><?php endif; ?>
        </table>

        <h2 style="margin-top:22px">Add template</h2>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_template">
            <div class="row">
                <div>
                    <label>Key</label>
                    <input name="tkey" placeholder="delivery_data" required>
                </div>
                <div>
                    <label>Label</label>
                    <input name="label" placeholder="Data delivery" required>
                </div>
            </div>
            <label>Pattern / keywords</label>
            <input name="pattern" placeholder="e.g. You have received %s of data" required>
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
