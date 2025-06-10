<?php
// Định nghĩa danh sách file cần ghép (thay bằng đường dẫn thực tế của bạn)
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

// File đầu ra
$outputFile = 'combined_file.php';
$combinedContent = '';

// Lặp qua từng file để ghép
foreach ($files as $index => $file) {
    if (file_exists($file)) {
        // Thêm comment bắt đầu file
        $fileName = basename($file); // Lấy tên file không bao gồm đường dẫn
        $combinedContent .= "// Bắt đầu file: $fileName\n";

        // Đọc nội dung file
        $content = file_get_contents($file);

        // Loại bỏ các thẻ <?php mở không cần thiết nếu không phải file đầu tiên
        if ($index > 0 && strpos($content, '<?php') === 0) {
            $content = preg_replace('/^\s*<\?php\s*/', '', $content, 1);
        }
        // Loại bỏ ?> đóng nếu có ở cuối file
        $content = preg_replace('/\s*\?>\s*$/', '', $content);

        // Thêm nội dung file
        $combinedContent .= $content . "\n";

        // Thêm comment kết thúc file
        $combinedContent .= "// Kết thúc file: $fileName\n\n";
    } else {
        echo "File $file không tồn tại!\n";
    }
}

$combinedContent = '<?php\n' . $combinedContent;

// Ghi nội dung vào file đầu ra
file_put_contents($outputFile, $combinedContent);

echo "Các file đã được ghép thành công vào $outputFile với comment ngăn cách.";
?>
