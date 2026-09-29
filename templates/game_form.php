<p class="text-sm text-court-500">
  <a href="<?= e(url('/manage')) ?>" class="hover:text-court-900">← Manage</a>
</p>
<h1 class="mt-3 font-display text-3xl"><?= e($title) ?></h1>

<form method="post" action="<?= e($action) ?>" class="mt-6 space-y-4 rounded-xl border border-court-100 bg-white p-4">
  <?= csrf_field() ?>
  <div>
    <label class="mb-1 block text-sm font-medium" for="season_id">Season</label>
    <select class="field-input" name="season_id" id="season_id" required>
      <?php foreach ($seasons as $season): ?>
        <option value="<?= (int) $season['id'] ?>" <?= ($game['season_id'] ?? null) == $season['id'] || (!$game && !empty($season['is_active'])) ? 'selected' : '' ?>>
          <?= e($season['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="mb-1 block text-sm font-medium" for="home_team_id">Team</label>
    <select class="field-input" name="home_team_id" id="home_team_id" required>
      <?php foreach ($teams as $team): ?>
        <option value="<?= (int) $team['id'] ?>" <?= ($game['home_team_id'] ?? null) == $team['id'] ? 'selected' : '' ?>>
          <?= e(team_label($team)) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="mb-1 block text-sm font-medium" for="away_team_id">Opponent</label>
    <select class="field-input" name="away_team_id" id="away_team_id" required>
      <?php foreach ($teams as $team): ?>
        <option value="<?= (int) $team['id'] ?>" <?= ($game['away_team_id'] ?? null) == $team['id'] ? 'selected' : '' ?>>
          <?= e(team_label($team)) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="mb-1 block text-sm font-medium" for="tipoff">Tipoff</label>
    <input class="field-input" type="datetime-local" name="tipoff" id="tipoff" required
           value="<?= e(tipoff_local_input($game['tipoff'] ?? null)) ?>">
  </div>
  <div>
    <label class="mb-1 block text-sm font-medium" for="status">Status</label>
    <select class="field-input" name="status" id="status">
      <option value="scheduled" <?= ($game['status'] ?? 'scheduled') === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
      <option value="final" <?= ($game['status'] ?? '') === 'final' ? 'selected' : '' ?>>Final</option>
    </select>
  </div>
  <button type="submit" class="w-full rounded-lg bg-orange-ball px-4 py-3 font-semibold text-white">Save game</button>
</form>
