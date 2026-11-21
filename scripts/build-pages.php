<?php

/**
 * README と docs/ を GitHub Pages 用の静的ページとして _site/ に生成する。
 *
 * 使い方: php scripts/build-pages.php [--out=_site] [--commit=<sha>]
 *
 * - Markdown の変換には Laravel の依存に含まれる league/commonmark を使う。
 * - 文書間の相対リンクはサイト内リンクに、画像は同じパスで配信する。
 *   上記以外でリポジトリに存在するファイル（LICENSE など）は GitHub 上の URL にする。
 * - 存在しないリンク先や見出しアンカーがあれば、何も書き出さずに失敗する。
 * - 変換対象はこのリポジトリ自身の Markdown だけなので、生の HTML を許可している。
 */

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

require __DIR__.'/../vendor/autoload.php';

const REPOSITORY_URL = 'https://github.com/kiyohara1021/laravel-admin-phpunit';

$root = dirname(__DIR__);
$opts = getopt('', ['out::', 'commit::']);
$outName = trim((string) ($opts['out'] ?? '_site'), '/');
$out = $root.'/'.$outName;
$commit = isset($opts['commit']) ? substr((string) $opts['commit'], 0, 7) : null;

// 元の Markdown => 出力パス。
$pages = [
    'README.md' => 'index.html',
    'README.en.md' => 'en.html',
    'docs/architecture.md' => 'docs/architecture.html',
    'docs/domain-model.md' => 'docs/domain-model.html',
    'docs/search-and-filtering.md' => 'docs/search-and-filtering.html',
    'docs/testing.md' => 'docs/testing.html',
    'CHANGELOG.md' => 'changelog.html',
    'CONTRIBUTING.md' => 'contributing.html',
    'SECURITY.md' => 'security.html',
    'SUPPORT.md' => 'support.html',
    'CODE_OF_CONDUCT.md' => 'code-of-conduct.html',
];

$nav = [
    ['index.html', '日本語'],
    ['en.html', 'English'],
    ['docs/architecture.html', '設計概要'],
    ['docs/domain-model.html', '状態モデル'],
    ['docs/search-and-filtering.html', '検索設計'],
    ['docs/testing.html', 'テスト戦略'],
    ['changelog.html', '変更履歴'],
];

$h = fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// 出力ファイル $from から、サイトルート相対の $to への相対パス。
$relative = function (string $from, string $to): string {
    $dir = dirname($from);

    return ($dir === '.' ? '' : str_repeat('../', substr_count($dir, '/') + 1)).$to;
};

// $dir 基準の相対パスを、リポジトリルート基準に正規化する。ルートより上に出たら null。
$normalize = function (string $dir, string $path): ?string {
    $parts = $dir === '.' ? [] : explode('/', $dir);
    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            if ($parts === []) {
                return null;
            }
            array_pop($parts);

            continue;
        }
        $parts[] = $segment;
    }

    return implode('/', $parts);
};

$environment = new Environment([
    'html_input' => 'allow',
    'allow_unsafe_links' => false,
    // GitHub と同じ形（接頭辞なし）の見出し ID を付け、#日本語 のようなアンカーを保つ。
    'heading_permalink' => ['id_prefix' => '', 'fragment_prefix' => '', 'insert' => 'after', 'symbol' => '#'],
]);
$environment->addExtension(new CommonMarkCoreExtension);
$environment->addExtension(new GithubFlavoredMarkdownExtension);
$environment->addExtension(new HeadingPermalinkExtension);
$converter = new MarkdownConverter($environment);

$errors = [];
$rendered = [];   // 元ファイル => ['title' => ..., 'html' => ..., 'ids' => [...]]
$assets = [];     // コピーする画像（リポジトリ相対パス）
$anchorChecks = []; // [元ファイル, 参照先の元ファイル, アンカー]

foreach ($pages as $src => $dest) {
    $file = $root.'/'.$src;
    if (! is_file($file)) {
        $errors[] = "{$src}: ファイルがありません";

        continue;
    }

    $markdown = (string) file_get_contents($file);
    $html = (string) $converter->convert($markdown);
    preg_match('/^#\s+(.+)$/m', $markdown, $m);
    $title = trim(preg_replace('/\s+/', ' ', $m[1] ?? $src));
    preg_match_all('/\sid="([^"]+)"/', $html, $idMatches);
    $ids = array_map('rawurldecode', $idMatches[1]);
    $srcDir = dirname($src);

    $html = preg_replace_callback(
        '/\b(href|src)="([^"]*)"/',
        function (array $m) use ($src, $srcDir, $dest, $pages, $root, $relative, $normalize, $h, &$errors, &$assets, &$anchorChecks): string {
            [$whole, $attr, $value] = $m;
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($value === '') {
                return $whole;
            }
            if ($value[0] === '#') {
                $anchorChecks[] = [$src, $src, rawurldecode(substr($value, 1))];

                return $whole;
            }
            if (preg_match('~^(?:[a-z][a-z0-9+.-]*:|//)~i', $value)) {
                return $whole;
            }

            [$path, $fragment] = array_pad(explode('#', $value, 2), 2, null);
            $target = $normalize($srcDir, rawurldecode($path));
            $suffix = $fragment === null ? '' : '#'.$fragment;

            if ($target === null || $target === '' || ! file_exists($root.'/'.$target)) {
                $errors[] = "{$src}: リンク先がありません: {$value}";

                return $whole;
            }
            if (isset($pages[$target])) {
                if ($fragment !== null) {
                    $anchorChecks[] = [$src, $target, rawurldecode($fragment)];
                }

                return $attr.'="'.$h($relative($dest, $pages[$target]).$suffix).'"';
            }
            if (is_file($root.'/'.$target) && preg_match('/\.(?:jpe?g|png|gif|svg|webp)$/i', $target)) {
                $assets[$target] = true;

                return $attr.'="'.$h($relative($dest, $target)).'"';
            }

            $kind = is_dir($root.'/'.$target) ? 'tree' : 'blob';

            return $attr.'="'.$h(REPOSITORY_URL.'/'.$kind.'/main/'.$target.$suffix).'"';
        },
        $html,
    );

    $rendered[$src] = ['title' => $title, 'html' => $html, 'ids' => $ids];
}

foreach ($anchorChecks as [$from, $to, $anchor]) {
    if (isset($rendered[$to]) && ! in_array($anchor, $rendered[$to]['ids'], true)) {
        $errors[] = "{$from}: {$to} に見出しアンカー #{$anchor} がありません";
    }
}

if ($errors !== []) {
    fwrite(STDERR, "ページを生成できません:\n  ".implode("\n  ", array_unique($errors))."\n");
    exit(1);
}

$template = function (string $title, string $body, string $dest, string $lang) use ($nav, $relative, $h, $commit): string {
    $css = $relative($dest, 'style.css');
    $links = '';
    foreach ($nav as [$href, $label]) {
        $current = $href === $dest ? ' aria-current="page"' : '';
        $links .= '<a href="'.$h($relative($dest, $href)).'"'.$current.'>'.$h($label).'</a>';
    }
    $links .= '<a href="'.REPOSITORY_URL.'">GitHub</a>';
    $stamp = date('Y-m-d').($commit !== null ? ' / '.$h($commit) : '');
    $heading = $dest === 'index.html' ? '' : ' | Laravel 管理画面 × PHPUnit';
    $title = $h($title).$heading;

    return <<<HTML
<!doctype html>
<html lang="{$lang}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
<link rel="stylesheet" href="{$css}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css" media="(prefers-color-scheme: light)">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css" media="(prefers-color-scheme: dark)">
</head>
<body>
<header><nav>{$links}</nav></header>
<main>
{$body}
</main>
<footer>生成: {$stamp}</footer>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script>if (window.hljs) { hljs.highlightAll(); }</script>
</body>
</html>
HTML;
};

if (is_dir($out)) {
    exec('rm -rf '.escapeshellarg($out));
}
mkdir($out, 0755, true);
copy(__DIR__.'/pages/style.css', $out.'/style.css');

foreach ($pages as $src => $dest) {
    $page = $rendered[$src];
    is_dir(dirname($out.'/'.$dest)) || mkdir(dirname($out.'/'.$dest), 0755, true);
    file_put_contents(
        $out.'/'.$dest,
        $template($page['title'], $page['html'], $dest, $src === 'README.en.md' ? 'en' : 'ja'),
    );
}

foreach (array_keys($assets) as $asset) {
    is_dir(dirname($out.'/'.$asset)) || mkdir(dirname($out.'/'.$asset), 0755, true);
    copy($root.'/'.$asset, $out.'/'.$asset);
}

echo 'generated '.count($pages).' pages and '.count($assets)." images in {$outName}/\n";
