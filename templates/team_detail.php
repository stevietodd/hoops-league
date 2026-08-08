<p class="text-sm text-court-500">
  <a href="<?= e(url('/teams')) ?>" class="hover:text-court-900">← Teams</a>
</p>

<div class="mt-4 flex items-center gap-3">
  <span class="flex h-12 w-12 items-center justify-center rounded-full bg-court-100 font-display"
        <?php if ($team['color']): ?>style="background: <?= e($team['color']) ?>22; color: <?= e($team['color']) ?>"<?php endif; ?>>
    <?= e(team_short($team)) ?>
  </span>
  <div>
    <h1 class="font-display text-3xl"><?= e($team['name']) ?></h1>
    <p class="text-sm text-court-500">Roster</p>
  </div>
</div>

<ul class="mt-6 divide-y divide-court-100 overflow-hidden rounded-xl border border-court-100 bg-white">
  <?php foreach ($players as $player): ?>
    <li class="flex items-center justify-between gap-3 px-4 py-3">
      <div>
        <p class="font-medium">
          <?= e($player['display_name']) ?>
          <?php if (!empty($player['is_captain'])): ?>
            <span class="ml-1 rounded-full bg-orange-ball/10 px-2 py-0.5 text-xs font-semibold text-orange-ball">Captain</span>
          <?php endif; ?>
        </p>
      </div>
      <?php if ($canManage): ?>
        <form method="post" action="<?= e(url('/teams/' . $team['id'] . '/roster/' . $player['id'] . '/remove')) ?>"
              onsubmit="return confirm('Remove <?= e($player['display_name']) ?>?');">
          <?= csrf_field() ?>
          <button type="submit" class="text-sm text-red-600">Remove</button>
        </form>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
  <?php if (!$players): ?>
    <li class="px-4 py-6 text-center text-court-500">No players on this roster yet.</li>
  <?php endif; ?>
</ul>

<?php if ($canManage): ?>
  <section class="mt-8">
    <h2 class="font-display text-xl">Add player</h2>
    <form method="post" action="<?= e(url('/teams/' . $team['id'] . '/roster/add')) ?>" class="mt-3 flex gap-2">
      <?= csrf_field() ?>
      <input class="field-input" type="text" name="display_name" placeholder="Player name" required>
      <button type="submit" class="rounded-lg bg-court-700 px-4 py-3 font-semibold text-white">Add</button>
    </form>
  </section>
<?php endif; ?>

<?php if ($isCommissioner && $players): ?>
  <section class="mt-8">
    <h2 class="font-display text-xl">Assign captain</h2>
    <form method="post" action="<?= e(url('/teams/' . $team['id'] . '/captain')) ?>" class="mt-3 flex gap-2">
      <?= csrf_field() ?>
      <select name="player_id" class="field-input" required>
        <?php foreach ($players as $player): ?>
          <option value="<?= (int) $player['id'] ?>" <?= !empty($player['is_captain']) ? 'selected' : '' ?>>
            <?= e($player['display_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="rounded-lg border border-court-200 bg-white px-4 py-3 font-medium">Set</button>
    </form>
  </section>
<?php endif; ?>

<p class="mt-8">
  <a href="<?= e(url('/schedule?team=' . $team['id'])) ?>" class="text-sm font-medium text-orange-ball">
    View <?= e($team['name']) ?> schedule →
  </a>
</p>
