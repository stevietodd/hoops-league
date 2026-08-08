<section>
  <p class="text-sm font-medium uppercase tracking-wide text-court-500">
    <?= $season ? e($season['name']) : 'No active season' ?>
  </p>
  <h1 class="mt-1 font-display text-3xl">Standings</h1>
</section>

<section class="mt-5">
  <?php if ($standings): ?>
    <div class="overflow-hidden rounded-xl border border-court-100 bg-white">
      <table class="w-full text-left text-sm">
        <thead class="bg-court-100/60 text-xs uppercase tracking-wide text-court-500">
          <tr>
            <th class="px-3 py-2.5 font-medium">#</th>
            <th class="px-3 py-2.5 font-medium">Team</th>
            <th class="px-3 py-2.5 text-right font-medium">W</th>
            <th class="px-3 py-2.5 text-right font-medium">L</th>
            <th class="px-3 py-2.5 text-right font-medium">PCT</th>
            <th class="px-3 py-2.5 text-right font-medium">Diff</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($standings as $i => $row): ?>
            <?php
              $played = $row['wins'] + $row['losses'];
              $pct = $played ? $row['wins'] / $played : null;
              $diff = $row['points_for'] - $row['points_against'];
            ?>
            <tr class="border-t border-court-100">
              <td class="px-3 py-3 text-court-500"><?= $i + 1 ?></td>
              <td class="px-3 py-3 font-semibold">
                <a href="<?= e(url('/teams/' . $row['team']['id'])) ?>"><?= e($row['team']['name']) ?></a>
              </td>
              <td class="px-3 py-3 text-right"><?= (int) $row['wins'] ?></td>
              <td class="px-3 py-3 text-right"><?= (int) $row['losses'] ?></td>
              <td class="px-3 py-3 text-right tabular-nums"><?= $pct === null ? '—' : number_format($pct, 3) ?></td>
              <td class="px-3 py-3 text-right tabular-nums <?= $diff > 0 ? 'text-court-700' : ($diff < 0 ? 'text-red-600' : '') ?>">
                <?= $diff > 0 ? '+' : '' ?><?= $diff ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="rounded-xl border border-dashed border-court-200 px-4 py-6 text-center text-court-500">No standings yet.</p>
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
            <p class="font-semibold"><?= e($team['name']) ?></p>
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
