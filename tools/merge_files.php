<?php
// Define the list of files to merge (replace with actual file paths if needed)
$files = [
    'index.php',
    'load-tool.php',
    'api-helper.php',
    'nft-holders.php',
    'export-holders.php',
    'nft-holders-list.php',
    'tools.css',
    'tools.js'
];

// Define the output file
$outputFile = 'merged_file.php';
$combinedContent = '';

// Loop through each file to merge
foreach ($files as $index => $file) {
    if (file_exists($file)) {
        // Add start comment for the file
        $fileName = basename($file); // Get the file name without path
        $combinedContent .= "// Start of file: $fileName\n";

        // Read the content of the file
        $content = file_get_contents($file);

        // Remove unnecessary <?php opening tags if not the first file
        if ($index > 0 && strpos($content, '<?php') === 0) {
            $content = preg_replace('/^\s*<\?php\s*/', '', $content, 1);
        }
        // Remove closing ?> tags if present at the end of the file
        $content = preg_replace('/\s*\?>\s*$/', '', $content);

        // Append the file content
        $combinedContent .= $content . "\n";

        // Add end comment for the file
        $combinedContent .= "// End of file: $fileName\n\n";
    } else {
        echo "File $file does not exist!\n";
    }
}

$combinedContent = '<?php\n' . $combinedContent;

// Write the combined content to the output file
file_put_contents($outputFile, $combinedContent);

echo "Files have been successfully merged into $outputFile with separating comments.";
?>
