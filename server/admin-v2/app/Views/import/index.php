<?php
/**
 * JSON import — one page serving both billboards and notifications.
 *
 * Everything the operator needs is on this page: the format, a template they can
 * copy or download, and the two ways in (paste or upload). No <script> — the CSP
 * is script-src 'self'.
 */
$sampleJson = json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>

<div class="page-head">
  <div>
    <h1><?= e($heading) ?></h1>
    <div class="sub">
      Paste the JSON or upload a file. Everything arrives as a <b>draft</b> — nothing
      reaches a customer until you review it and publish.
    </div>
  </div>
  <div class="page-head__actions">
    <a class="btn btn--ghost" href="<?= e(url($sampleUrl)) ?>"><?= icon('download', 18) ?> Download template</a>
    <a class="btn btn--ghost" href="<?= e(url($backUrl)) ?>">Cancel</a>
  </div>
</div>

<div class="grid two">
  <div class="card">
    <form method="post" action="<?= e(url($postUrl)) ?>" enctype="multipart/form-data" data-once>
      <?= App\Core\Csrf::field() ?>

      <div class="field">
        <label for="import-file">Upload a .json file</label>
        <input id="import-file" type="file" name="file" accept="application/json,.json">
        <span class="hint">Up to 1 MB and <?= (int) App\Services\JsonImporter::MAX_ITEMS ?> entries per import.</span>
      </div>

      <div class="field mt">
        <label for="import-json">…or paste the JSON here</label>
        <textarea id="import-json" name="json" rows="18" spellcheck="false"
                  placeholder="Paste your JSON, or start from the template on the right."></textarea>
        <span class="hint">If you do both, the uploaded file wins.</span>
      </div>

      <div class="row mt" style="gap:8px">
        <button class="btn" type="submit"><?= icon('check', 18) ?> Import as drafts</button>
        <a class="btn btn--ghost" href="<?= e(url($backUrl)) ?>">Back</a>
      </div>

      <p class="muted small mt">
        The whole file is checked first. If one entry is wrong, nothing is imported and
        you are told which entry and why — so you never end up with half an import.
      </p>
    </form>
  </div>

  <div class="card">
    <div class="card__head"><h3>Template</h3></div>
    <p class="muted small">
      Copy this, replace the values, and paste it back. Extra keys are ignored, so you can
      keep your own notes in the file.
    </p>
    <div class="row mt" style="gap:8px">
      <button class="btn btn--ghost btn--sm" type="button" data-copy="<?= e($sampleJson) ?>">Copy template</button>
      <a class="btn btn--ghost btn--sm" href="<?= e(url($sampleUrl)) ?>">Download .json</a>
    </div>
    <pre class="mt mono" style="max-height:340px;overflow:auto;background:var(--grouped);border:1px solid var(--divider);border-radius:10px;padding:12px;font-size:12px;line-height:1.5;white-space:pre"><code><?= e($sampleJson) ?></code></pre>

    <div class="card__head mt"><h3>What each field means</h3></div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Field</th><th>Required?</th><th>Meaning</th></tr></thead>
        <tbody>
          <?php foreach ($fields as [$field, $required, $meaning]): ?>
            <tr>
              <td class="mono small"><?= e($field) ?></td>
              <td class="muted small nowrap"><?= e($required) ?></td>
              <td class="small"><?= e($meaning) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
