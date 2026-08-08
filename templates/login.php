<div class="mx-auto max-w-sm pt-10">
  <h1 class="font-display text-3xl text-court-900">Sign in</h1>
  <p class="mt-2 text-court-500">Captains and staff log in to report scores and manage the league. Anyone can browse standings and schedule without an account.</p>
  <form method="post" action="<?= e(url('/login')) ?>" class="mt-8 space-y-4">
    <?= csrf_field() ?>
    <div>
      <label class="mb-1 block text-sm font-medium" for="email">Email</label>
      <input class="field-input" type="email" name="email" id="email" autocomplete="username" required>
    </div>
    <div>
      <label class="mb-1 block text-sm font-medium" for="password">Password</label>
      <input class="field-input" type="password" name="password" id="password" autocomplete="current-password" required>
    </div>
    <button type="submit" class="w-full rounded-lg bg-orange-ball px-4 py-3 font-semibold text-white hover:bg-orange-600">
      Log in
    </button>
  </form>
</div>
