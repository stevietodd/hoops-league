<p class="text-sm text-court-500">
  <a href="<?= e(url('/manage')) ?>" class="hover:text-court-900">← Manage</a>
  <?php if (!empty($tournament)): ?>
    · <a href="<?= e(url('/playoffs')) ?>" class="hover:text-court-900">View bracket</a>
  <?php endif; ?>
</p>
<h1 class="mt-3 font-display text-3xl"><?= e($title) ?></h1>

<?php if (empty($tournament)): ?>
  <p class="mt-1 text-sm text-court-500">
    Seeds from current regular-season standings (1 = top). Set a tipoff for each opening game — they can differ (e.g. 7 PM and 8 PM waves).
  </p>

  <form method="post" action="<?= e(url('/manage/playoffs/new')) ?>" class="mt-6 space-y-4 rounded-xl border border-court-100 bg-white p-4" id="create-playoffs-form">
    <?= csrf_field() ?>
    <div>
      <label class="mb-1 block text-sm font-medium" for="bracket_size">Bracket size</label>
      <select class="field-input" name="bracket_size" id="bracket_size" required>
        <option value="8" <?= count($standings) >= 8 ? 'selected' : '' ?>>8 teams</option>
        <option value="4" <?= count($standings) < 8 ? 'selected' : '' ?>>4 teams</option>
      </select>
    </div>
    <div>
      <label class="mb-1 block text-sm font-medium" for="location">Location</label>
      <input class="field-input" type="text" name="location" id="location" value="">
    </div>

    <div>
      <p class="mb-2 text-sm font-medium">First-round tipoffs</p>
      <div class="space-y-3" id="opening-tipoffs"></div>
    </div>

    <div>
      <p class="mb-2 text-sm font-medium">Seeding preview</p>
      <ol class="space-y-1 text-sm text-court-700">
        <?php foreach ($standings as $i => $row): ?>
          <li>
            <span class="text-court-400">#<?= $i + 1 ?></span>
            <?= e(team_label($row['team'])) ?>
            <span class="text-court-400">(<?= (int) $row['wins'] ?>–<?= (int) $row['losses'] ?>)</span>
          </li>
        <?php endforeach; ?>
        <?php if (!$standings): ?>
          <li class="text-court-500">No teams in standings.</li>
        <?php endif; ?>
      </ol>
    </div>

    <button type="submit" class="w-full rounded-lg bg-orange-ball px-4 py-3 font-semibold text-white">
      Create bracket
    </button>
  </form>
  <script type="application/json" id="opening-matchups-data"><?= json_encode($openingMatchupsBySize ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
  <script>
    (function () {
      var data = JSON.parse(document.getElementById('opening-matchups-data').textContent || '{}');
      var sizeEl = document.getElementById('bracket_size');
      var box = document.getElementById('opening-tipoffs');
      function render() {
        var size = sizeEl.value;
        var matchups = data[size] || [];
        box.innerHTML = '';
        matchups.forEach(function (m, i) {
          var wrap = document.createElement('div');
          var label = document.createElement('label');
          label.className = 'mb-1 block text-xs text-court-500';
          label.htmlFor = 'tipoff_' + i;
          label.textContent = m.label;
          var input = document.createElement('input');
          input.className = 'field-input';
          input.type = 'datetime-local';
          input.name = 'tipoffs[' + i + ']';
          input.id = 'tipoff_' + i;
          input.required = true;
          wrap.appendChild(label);
          wrap.appendChild(input);
          box.appendChild(wrap);
        });
      }
      sizeEl.addEventListener('change', render);
      render();
    })();
  </script>
<?php else: ?>
  <p class="mt-1 text-sm text-court-500">
    Set tipoffs for any round — including later games that still show as Winner A vs Winner B. Letters stay fixed to bracket position.
  </p>

  <form method="post" action="<?= e(url('/manage/playoffs/games')) ?>" class="mt-6 space-y-4 rounded-xl border border-court-100 bg-white p-4">
    <?= csrf_field() ?>
    <h2 class="font-display text-xl">Game times</h2>
    <?php foreach ($matchups as $m): ?>
      <?php
        $key = (int) $m['round'] . '_' . (int) $m['matchup_index'];
        $letter = $m['letter'] ?? '';
        $required = (int) $m['round'] === 1;
      ?>
      <div class="border-t border-court-100 pt-4 first:border-0 first:pt-0">
        <p class="mb-2 text-sm font-medium">
          <?= e($m['round_label'] ?? '') ?> · Game <?= e($letter) ?>:
          <?= e($m['label_a'] ?? 'TBD') ?>
          <span class="text-court-400">vs</span>
          <?= e($m['label_b'] ?? 'TBD') ?>
          <?php if (!empty($m['game']) && ($m['game']['status'] ?? '') === 'final'): ?>
            <span class="text-court-400">(final)</span>
          <?php endif; ?>
        </p>
        <div class="grid gap-3 sm:grid-cols-2">
          <div>
            <label class="mb-1 block text-xs text-court-500" for="tipoff_<?= e($key) ?>">Tipoff<?= $required ? '' : ' (optional)' ?></label>
            <input class="field-input" type="datetime-local" name="matchups[<?= e($key) ?>][tipoff]" id="tipoff_<?= e($key) ?>"
                   value="<?= e(tipoff_local_input($m['tipoff'] ?? null)) ?>" <?= $required ? 'required' : '' ?>>
          </div>
          <div>
            <label class="mb-1 block text-xs text-court-500" for="loc_<?= e($key) ?>">Location</label>
            <input class="field-input" type="text" name="matchups[<?= e($key) ?>][location]" id="loc_<?= e($key) ?>"
                   value="<?= e($m['location'] ?? '') ?>">
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (empty($matchups)): ?>
      <p class="text-sm text-court-500">No playoff matchups yet.</p>
    <?php else: ?>
      <button type="submit" class="w-full rounded-lg bg-orange-ball px-4 py-3 font-semibold text-white">
        Save schedule
      </button>
    <?php endif; ?>
  </form>

  <form method="post" action="<?= e(url('/manage/playoffs/seeds')) ?>" class="mt-6 space-y-4 rounded-xl border border-court-100 bg-white p-4">
    <?= csrf_field() ?>
    <h2 class="font-display text-xl">Seeding</h2>
    <?php if (!$canEditSeeds): ?>
      <p class="text-sm text-court-500">Seeds are locked after a first-round game is final.</p>
    <?php endif; ?>
    <div class="space-y-3">
      <?php for ($seed = 1; $seed <= (int) $tournament['bracket_size']; $seed++): ?>
        <?php $current = (int) ($seeds[$seed]['team_id'] ?? 0); ?>
        <div>
          <label class="mb-1 block text-sm font-medium" for="seed_<?= $seed ?>">Seed #<?= $seed ?></label>
          <select class="field-input" name="seed[<?= $seed ?>]" id="seed_<?= $seed ?>" <?= $canEditSeeds ? 'required' : 'disabled' ?>>
            <?php foreach ($teams as $team): ?>
              <option value="<?= (int) $team['id'] ?>" <?= $current === (int) $team['id'] ? 'selected' : '' ?>>
                <?= e(team_label($team)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endfor; ?>
    </div>
    <?php if ($canEditSeeds): ?>
      <button type="submit" class="w-full rounded-lg border border-court-200 bg-white px-4 py-3 font-semibold text-court-900">
        Apply seeding
      </button>
    <?php endif; ?>
  </form>
<?php endif; ?>
