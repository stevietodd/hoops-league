<p class="text-sm text-court-500">
  <a href="<?= e(url('/teams')) ?>" class="hover:text-court-900">← Teams</a>
</p>

<div class="mt-4 flex items-center gap-3">
  <span class="flex h-12 w-12 items-center justify-center rounded-full bg-court-100 font-display"
        <?php if ($team['color']): ?>style="background: <?= e($team['color']) ?>22; color: <?= e($team['color']) ?>"<?php endif; ?>>
    <?= e(team_short($team)) ?>
  </span>
  <div>
    <h1 class="font-display text-3xl"><?= e(team_label($team)) ?></h1>
    <p class="text-sm text-court-500">Roster</p>
  </div>
</div>

<?php if ($canManage): ?>
  <section class="mt-6 rounded-xl border border-court-100 bg-white p-4">
    <h2 class="font-display text-lg">Team display name</h2>
    <p class="mt-1 text-sm text-court-500">Shown on standings, schedule, and rosters.</p>
    <form method="post" action="<?= e(url('/teams/' . $team['id'] . '/update')) ?>" class="mt-3 flex gap-2">
      <?= csrf_field() ?>
      <input class="field-input" type="text" name="display_name" value="<?= e($team['display_name']) ?>"
             placeholder="Team display name" required aria-label="Team display name">
      <button type="submit" class="shrink-0 rounded-lg border border-court-200 bg-white px-4 py-3 font-medium">Save</button>
    </form>
  </section>
<?php endif; ?>

<ul class="mt-6 divide-y divide-court-100 overflow-hidden rounded-xl border border-court-100 bg-white">
  <?php foreach ($players as $player): ?>
    <li class="px-4 py-3">
      <div class="flex items-start justify-between gap-3">
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
      </div>
      <?php if ($canManage): ?>
        <form method="post" action="<?= e(url('/teams/' . $team['id'] . '/roster/' . $player['id'] . '/update')) ?>"
              class="mt-3 grid gap-2 sm:grid-cols-4">
          <?= csrf_field() ?>
          <input class="field-input" type="text" name="first_name" value="<?= e($player['first_name']) ?>" placeholder="First" aria-label="First name">
          <input class="field-input" type="text" name="last_initial" maxlength="1" value="<?= e($player['last_initial']) ?>" placeholder="Last initial" aria-label="Last initial">
          <input class="field-input" type="text" name="display_name" value="<?= e($player['display_name']) ?>" placeholder="Display name" required aria-label="Display name">
          <div class="flex gap-2">
            <input class="field-input" type="text" name="current_ranking" value="<?= e($player['current_ranking']) ?>" placeholder="Ranking" aria-label="Ranking">
            <button type="submit" class="shrink-0 rounded-lg border border-court-200 bg-white px-3 py-2 text-sm font-medium">Save</button>
          </div>
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
    <form method="post" action="<?= e(url('/teams/' . $team['id'] . '/roster/add')) ?>" class="mt-3 grid gap-2 sm:grid-cols-2">
      <?= csrf_field() ?>
      <input class="field-input" type="text" name="first_name" placeholder="First name">
      <input class="field-input" type="text" name="last_initial" maxlength="1" placeholder="Last initial">
      <input class="field-input" type="text" name="display_name" placeholder="Display name (public)">
      <input class="field-input" type="text" name="current_ranking" placeholder="Ranking" value="<?= e($team['captain_ranking'] ?? '') ?>">
      <button type="submit" class="rounded-lg bg-court-700 px-4 py-3 font-semibold text-white sm:col-span-2">Add player</button>
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
    View <?= e(team_label($team)) ?> schedule →
  </a>
</p>
