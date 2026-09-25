# Themes (Engine: Milestone 2)

```
themes/{slug}/
  theme.json   { "slug","name","version","sidebars":[],"templates":[] }
  templates/   home.php page.php single.php archive.php 404.php
  assets/      css/ js/ img/
```

قالب فعال در `options.active_theme` + جدول `themes` (M1 آماده).
رندر فرانت در M2 از `ThemeManager` عبور می‌کند با fallback به Core views.
