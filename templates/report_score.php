<p class="text-sm text-court-500">
  <a href="<?= e(url('/games/' . $game['id'])) ?>" class="hover:text-court-900">← Game</a>
</p>
<h1 class="mt-3 font-display text-3xl">Report score</h1>
<p class="mt-1 text-court-500"><?= e($game['matchup_label']) ?></p>

<form method="post" action="<?= e(url('/games/' . $game['id'] . '/report')) ?>" class="mt-6 space-y-4 rounded-xl border border-court-100 bg-white p-4">
  <?= csrf_field() ?>
  <?php
    $leftField = !empty($game['left_is_home']) ? 'home_score' : 'away_score';
    $rightField = !empty($game['left_is_home']) ? 'away_score' : 'home_score';
    $leftValue = !empty($game['left_is_home']) ? ($game['home_score'] ?? '') : ($game['away_score'] ?? '');
    $rightValue = !empty($game['left_is_home']) ? ($game['away_score'] ?? '') : ($game['home_score'] ?? '');
  ?>
  <div>
    <label class="mb-1 block text-sm font-medium" for="<?= e($leftField) ?>"><?= e($game['left_name']) ?></label>
    <input class="field-input" type="number" min="0" inputmode="numeric" name="<?= e($leftField) ?>" id="<?= e($leftField) ?>" required
           value="<?= e((string) $leftValue) ?>">
  </div>
  <div>
    <label class="mb-1 block text-sm font-medium" for="<?= e($rightField) ?>"><?= e($game['right_name']) ?></label>
    <input class="field-input" type="number" min="0" inputmode="numeric" name="<?= e($rightField) ?>" id="<?= e($rightField) ?>" required
           value="<?= e((string) $rightValue) ?>">
  </div>
  <button type="submit" class="w-full rounded-lg bg-orange-ball px-4 py-3 font-semibold text-white">Save final score</button>
</form>
