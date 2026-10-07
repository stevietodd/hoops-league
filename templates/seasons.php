<h1 class="font-display text-3xl">Seasons</h1>
<p class="mt-1 text-sm text-court-500">League history will live here as seasons wrap up.</p>

<ul class="mt-6 space-y-3">
  <?php foreach ($seasons as $s): ?>
    <?php
      $status = $s['status'] ?? ($s['is_active'] ? 'active' : 'archived');
      $statusLabel = match ($status) {
          'active' => 'Active',
          'playoffs' => 'Playoffs',
          'archived' => 'Archived',
          default => ucfirst((string) $status),
      };
      $isCurrent = !empty($s['is_active']);
    ?>
    <li class="rounded-xl border border-court-100 bg-white px-4 py-4 <?= $isCurrent ? 'ring-1 ring-orange-ball/30' : '' ?>">
      <div class="flex items-start justify-between gap-3">
        <div>
          <p class="font-semibold"><?= e($s['name']) ?></p>
          <p class="mt-1 text-sm text-court-500">
            <?= e($statusLabel) ?>
            <?php if ($s['start_date'] || $s['end_date']): ?>
              ·
              <?= e(trim(($s['start_date'] ?? '') . ($s['start_date'] && $s['end_date'] ? ' – ' : '') . ($s['end_date'] ?? ''))) ?>
            <?php endif; ?>
          </p>
          <?php if (!empty($s['champion_display_name'])): ?>
            <p class="mt-1 text-sm text-court-600">
              Champion:
              <?= e(team_label([
                  'display_name' => $s['champion_display_name'],
                  'team_number' => $s['champion_team_number'],
              ])) ?>
            </p>
            <?php if (!empty($s['champion_players'])): ?>
              <p class="mt-1 text-sm text-court-500">
                <?= e(implode(', ', array_map(
                    static fn (array $p): string => $p['display_name'] . (!empty($p['is_captain']) ? ' (C)' : ''),
                    $s['champion_players']
                ))) ?>
              </p>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        <?php if ($isCurrent): ?>
          <span class="rounded-full bg-orange-ball/10 px-2 py-0.5 text-xs font-semibold text-orange-ball">Current</span>
        <?php endif; ?>
      </div>
      <?php if ($isCurrent): ?>
        <div class="mt-3 flex flex-wrap gap-3 text-sm">
          <a href="<?= e(url('/')) ?>" class="font-medium text-orange-ball">Home</a>
          <a href="<?= e(url('/standings')) ?>" class="font-medium text-orange-ball">Standings</a>
          <a href="<?= e(url('/schedule')) ?>" class="font-medium text-orange-ball">Schedule</a>
          <a href="<?= e(url('/playoffs')) ?>" class="font-medium text-orange-ball">Playoffs</a>
        </div>
      <?php else: ?>
        <p class="mt-3 text-xs text-court-400">Full season archive coming later.</p>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
  <?php if (!$seasons): ?>
    <li class="rounded-xl border border-dashed border-court-200 px-4 py-8 text-center text-court-500">No seasons yet.</li>
  <?php endif; ?>
</ul>
