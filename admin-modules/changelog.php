<?php
// Changelog pagina functie (laadt changelog.txt)
function whatsapp_paras_changelog_page() {

    // Pad naar changelog.txt in plugin root
    $file = plugin_dir_path(__DIR__) . 'changelog.txt';
    $content = '';
    if ($file && file_exists($file) && is_readable($file)) {
        $content = file_get_contents($file);
$content = htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$content = str_replace("\r\n", "\n", $content); // Windows naar Unix newlines
    } else {
        $content = 'Changelog bestand niet gevonden of niet leesbaar.';
    }
?>
    <div class="wrap">
    <h1>Changelog Core plugin FC De Paras</h1>
    <pre style="white-space: pre-wrap; font-family: monospace;"><?php echo $content; ?></pre>
</div>
<?php
}
