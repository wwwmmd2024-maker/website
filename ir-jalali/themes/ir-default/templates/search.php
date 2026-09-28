<?php
/** IR Default — search results. Data: $query, $posts, $total */
$archiveTitle = 'نتایج جستجو برای «' . ($query ?? '') . '» (' . ($total ?? 0) . ')';
require __DIR__ . '/archive.php';
