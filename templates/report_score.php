<p class="text-sm text-court-500">
  <a href="<?= e(url('/games/' . $game['id'])) ?>" class="hover:text-court-900">← Game</a>
</p>
<h1 class="mt-3 font-display text-3xl">Report score</h1>
<p class="mt-1 text-court-500"><?= e($game['away_name']) ?> @ <?= e($game['home_name']) ?></p>

<form method="post" action="<?= e(url('/games/' . $game['id'] . '/report')) ?>" class="mt-6 space-y-4 rounded-xl border border-court-100 bg-white p-4">
  <?= csrf_field() ?>
  <div>
    <label class="mb-1 block text-sm font-medium" for="away_score"><?= e($game['away_name']) ?> (away)</label>
    <input class="field-input" type="number" min="0" inputmode="numeric" name="away_score" id="away_score" required
           value="<?= e((string) ($game['away_score'] ?? '')) ?>">
  </div>
  <div>
    <label class="mb-1 block text-sm font-medium" for="home_score"><?= e($game['home_name']) ?> (home)</label>
    <input class="field-input" type="number" min="0" inputmode="numeric" name="home_score" id="home_score" required
           value="<?= e((string) ($game['home_score'] ?? '')) ?>">
  </div>
  <button type="submit" class="w-full rounded-lg bg-orange-ball px-4 py-3 font-semibold text-white">Save final score</button>
</form>
