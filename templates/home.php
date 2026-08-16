<section>
  <p class="text-sm font-medium uppercase tracking-wide text-court-500">
    <?= $season ? e($season['name']) : 'No active season' ?>
  </p>
</section>

<?php if ($upcomingGames): ?>
  <?php
    $tz = new DateTimeZone((string) config('timezone'));
    $today = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
    $isToday = $upcomingDate === $today;
    $dateLabel = (new DateTimeImmutable($upcomingDate, $tz))->format('D, M j');
  ?>
  <section class="mt-4">
    <div class="flex items-end justify-between gap-3">
      <div>
        <h1 class="font-display text-3xl"><?= $isToday ? 'Today' : 'Next up' ?></h1>
        <p class="mt-1 text-sm text-court-500"><?= e($dateLabel) ?></p>
      </div>
      <a href="<?= e(url('/schedule')) ?>" class="text-sm font-medium text-orange-ball">Full schedule</a>
    </div>
    <ul class="mt-4 space-y-3">
      <?php foreach ($upcomingGames as $game): ?>
        <li>
          <a href="<?= e(url('/games/' . $game['id'])) ?>" class="block rounded-xl border border-court-100 bg-white px-4 py-3">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-xs font-medium uppercase tracking-wide text-court-400">
                  <?php if (($game['phase'] ?? 'regular') === 'playoff'): ?>
                    <span class="text-orange-ball">Playoff</span> ·
                  <?php endif; ?>
                  <?= e(format_tipoff($game['tipoff'], 'g:i A')) ?>
                </p>
                <p class="mt-1 font-semibold">
                  <?= e($game['away_name']) ?>
                  <span class="font-normal text-court-400">@</span>
                  <?= e($game['home_name']) ?>
                </p>
                <?php if ($game['location']): ?>
                  <p class="mt-1 text-sm text-court-500"><?= e($game['location']) ?></p>
                <?php endif; ?>
              </div>
              <div class="text-right">
                <?php if ($game['status'] === 'final' && $game['home_score'] !== null): ?>
                  <p class="font-display text-lg leading-none"><?= (int) $game['away_score'] ?>–<?= (int) $game['home_score'] ?></p>
                  <p class="mt-1 text-xs uppercase text-court-400">Final</p>
                <?php else: ?>
                  <span class="rounded-full bg-court-100 px-2 py-1 text-xs text-court-600">Scheduled</span>
                <?php endif; ?>
              </div>
            </div>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<section class="<?= $upcomingGames ? 'mt-10' : 'mt-4' ?>">
  <h<?= $upcomingGames ? '2' : '1' ?> class="font-display <?= $upcomingGames ? 'text-2xl' : 'text-3xl' ?>">Standings</h<?= $upcomingGames ? '2' : '1' ?>>
  <?php if ($standings): ?>
    <div class="mt-4 overflow-x-auto rounded-xl border border-court-100 bg-white">
      <table class="w-full min-w-[36rem] text-left text-sm">
        <thead class="bg-court-100/60 text-xs uppercase tracking-wide text-court-500">
          <tr>
            <th class="px-3 py-2.5 font-medium">#</th>
            <th class="px-3 py-2.5 font-medium">Team</th>
            <th class="px-3 py-2.5 text-right font-medium">W</th>
            <th class="px-3 py-2.5 text-right font-medium">L</th>
            <th class="px-3 py-2.5 text-right font-medium">PCT</th>
            <th class="px-3 py-2.5 text-right font-medium" title="Offensive points per game">OFF</th>
            <th class="px-3 py-2.5 text-right font-medium" title="Points allowed per game">DEF</th>
            <th class="px-3 py-2.5 text-right font-medium" title="OFF − DEF">Diff</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($standings as $i => $row): ?>
            <?php
              $played = $row['wins'] + $row['losses'];
              $pct = $played ? $row['wins'] / $played : null;
              $diff = $row['diff'];
            ?>
            <tr class="border-t border-court-100">
              <td class="px-3 py-3 text-court-500"><?= $i + 1 ?></td>
              <td class="px-3 py-3 font-semibold">
                <a href="<?= e(url('/teams/' . $row['team']['id'])) ?>"><?= e(team_label($row['team'])) ?></a>
              </td>
              <td class="px-3 py-3 text-right"><?= (int) $row['wins'] ?></td>
              <td class="px-3 py-3 text-right"><?= (int) $row['losses'] ?></td>
              <td class="px-3 py-3 text-right tabular-nums"><?= $pct === null ? '—' : number_format($pct, 3) ?></td>
              <td class="px-3 py-3 text-right tabular-nums"><?= e(format_avg($row['off'])) ?></td>
              <td class="px-3 py-3 text-right tabular-nums"><?= e(format_avg($row['def'])) ?></td>
              <td class="px-3 py-3 text-right tabular-nums <?= ($diff ?? 0) > 0 ? 'text-court-700' : (($diff ?? 0) < 0 ? 'text-red-600' : '') ?>">
                <?= e(format_diff($diff)) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="mt-4 rounded-xl border border-dashed border-court-200 px-4 py-6 text-center text-court-500">No standings yet.</p>
  <?php endif; ?>
</section>

<section class="mt-10">
  <h2 class="font-display text-2xl">Pick your team</h2>
  <p class="mt-1 text-sm text-court-500">Jump to a roster</p>
  <ul class="mt-4 space-y-3">
    <?php foreach ($teams as $team): ?>
      <li>
        <a href="<?= e(url('/teams/' . $team['id'])) ?>"
           class="flex items-center justify-between rounded-xl border border-court-100 bg-white px-4 py-4">
          <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-court-100 font-display text-sm"
                  <?php if ($team['color']): ?>style="background: <?= e($team['color']) ?>22; color: <?= e($team['color']) ?>"<?php endif; ?>>
              <?= e(team_short($team)) ?>
            </span>
            <p class="font-semibold"><?= e(team_label($team)) ?></p>
          </div>
          <span class="text-court-300">›</span>
        </a>
      </li>
    <?php endforeach; ?>
    <?php if (!$teams): ?>
      <li class="rounded-xl border border-dashed border-court-200 px-4 py-6 text-center text-court-500">No teams yet.</li>
    <?php endif; ?>
  </ul>
</section>

<?php if ($navIsCommissioner): ?>
  <p class="mt-8 sm:hidden">
    <a href="<?= e(url('/manage')) ?>" class="block rounded-lg border border-court-200 bg-white px-4 py-3 text-center font-medium">
      Commissioner manage →
    </a>
  </p>
<?php endif; ?>
