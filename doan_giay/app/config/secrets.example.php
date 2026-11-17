<?php
// Sao chép file này thành secrets.php rồi điền khóa API nếu muốn chatbot dùng mô hình AI (Claude) để diễn đạt câu trả lời.
// File secrets.php đã được .gitignore để KHÔNG bị đưa lên GitHub. Để trống/không tạo file => chatbot chạy chế độ theo luật (vẫn dùng RAG).
if (!defined('SECURE_CHECK')) { exit('Access denied'); }
const ANTHROPIC_API_KEY = '';

