<?php
  $played = static function (array $row): int {
      return $row['wins'] + $row['losses'];
  };
?>
<h1 class="font-display text-3xl">Standings</h1>
<p class="mt-1 text-sm text-court-500">
  <?php if ($season): ?>
    <a href="<?= e(url('/seasons')) ?>" class="hover:text-court-900"><?= e($season['name']) ?></a>
  <?php else: ?>
    No active season
  <?php endif; ?>
</p>

<?php if ($standings): ?>
  <div class="mt-6 overflow-x-auto rounded-xl border border-court-100 bg-white">
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
            $gp = $played($row);
            $pct = $gp ? $row['wins'] / $gp : null;
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
  <p class="mt-8 text-court-500">No standings to show yet.</p>
<?php endif; ?>
