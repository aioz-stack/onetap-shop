<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <link rel="icon" type="image/svg+xml" href="/images/logo/logo-icon.svg" />
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0"
    />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title inertia>{{ config('app.name', 'Onetap Marketplace') }}</title>
    <script>
      // Applied before paint so dark mode does not flash on first load.
      try {
        if (JSON.parse(localStorage.getItem('darkMode') || 'false') === true) {
          document.documentElement.classList.add('dark');
        }
      } catch (e) {}
    </script>
    @viteReactRefresh
    @vite(['resources/css/style.css', 'resources/js/app.jsx'])
    @inertiaHead
  </head>
  <body class="bg-gray-50 dark:bg-gray-900">
    @inertia
  </body>
</html>
