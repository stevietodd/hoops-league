<p class="text-sm text-court-500">
  <a href="<?= e(url('/manage')) ?>" class="hover:text-court-900">← Manage</a>
</p>
<h1 class="mt-3 font-display text-3xl">Start playoffs</h1>
<p class="mt-1 text-sm text-court-500">
  Seeds from current regular-season standings (1 = top). First-round tipoff applies to all opening games; later rounds get a default tipoff one week after each prior game (editable on the game page).
</p>

<form method="post" action="<?= e(url('/manage/playoffs/new')) ?>" class="mt-6 space-y-4 rounded-xl border border-court-100 bg-white p-4">
  <?= csrf_field() ?>
  <div>
    <label class="mb-1 block text-sm font-medium" for="bracket_size">Bracket size</label>
    <select class="field-input" name="bracket_size" id="bracket_size" required>
      <option value="8" <?= count($standings) >= 8 ? 'selected' : '' ?>>8 teams</option>
      <option value="4" <?= count($standings) < 8 ? 'selected' : '' ?>>4 teams</option>
    </select>
  </div>
  <div>
    <label class="mb-1 block text-sm font-medium" for="tipoff">First-round tipoff</label>
    <input class="field-input" type="datetime-local" name="tipoff" id="tipoff" required>
  </div>
  <div>
    <label class="mb-1 block text-sm font-medium" for="location">Location</label>
    <input class="field-input" type="text" name="location" id="location" value="">
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
