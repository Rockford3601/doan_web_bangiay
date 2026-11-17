<?php
// RAG (Retrieval-Augmented Generation) cho chatbot, viết bằng PHP thuần:
//   1) RETRIEVE : tìm các đoạn liên quan trong "kho tri thức" (bảng knowledge_base + danh sách sản phẩm) bằng thuật toán BM25.
//   2) AUGMENT  : đưa các đoạn tìm được (+ kết quả tính size) vào prompt làm NGỮ CẢNH.
//   3) GENERATE : nếu có API key thì mô hình AI diễn đạt câu trả lời dựa trên ngữ cảnh; nếu không thì trả lời trích xuất từ ngữ cảnh.
if (!defined('SECURE_CHECK')) {
    exit('Access denied');
}

// Chuyển tiếng Việt có dấu -> không dấu, chữ thường (để tìm kiếm không phụ thuộc dấu)
function vn_normalize(string $s): string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        $groups = [
            'a' => 'àáạảãâầấậẩẫăằắặẳẵ', 'e' => 'èéẹẻẽêềếệểễ', 'i' => 'ìíịỉĩ',
            'o' => 'òóọỏõôồốộổỗơờớợởỡ', 'u' => 'ùúụủũưừứựửữ', 'y' => 'ỳýỵỷỹ', 'd' => 'đ',
        ];
        foreach ($groups as $base => $chars) {
            foreach (preg_split('//u', $chars, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
                $map[$ch] = $base;
            }
        }
    }
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/\p{Mn}+/u', '', $s) ?? $s;   // bỏ dấu rời (khi gõ dạng tổ hợp)
    $s = strtr($s, $map);
    return trim(preg_replace('/[^a-z0-9\s.,#]/u', ' ', $s) ?? $s);
}

function rag_tokens(string $s): array
{
    static $stop = null;
    if ($stop === null) {
        $stop = array_flip(['la', 'va', 'cua', 'co', 'khong', 'cho', 'toi', 'minh', 'ban', 'the', 'nao', 'gi', 'o', 'de', 'bao', 'nhieu',
            'voi', 'nay', 'a', 'ah', 'nhe', 'nha', 'vay', 'duoc', 'khi', 'thi', 'em', 'anh', 'chi', 'shop', 'mot', 'cac', 'nhung', 'ma', 'thay', 'muon', 'hoi']);
    }
    $out = [];
    foreach (preg_split('/[^a-z0-9]+/', vn_normalize($s), -1, PREG_SPLIT_NO_EMPTY) as $w) {
        if (isset($stop[$w])) continue;
        if (strlen($w) < 2 && !ctype_digit($w)) continue;
        $out[] = $w;
    }
    return $out;
}

// Các "tài liệu" để tìm kiếm: bài viết chính sách + sản phẩm đang bán
function rag_documents(): array
{
    static $docs = null;
    if ($docs !== null) {
        return $docs;
    }
    $docs = [];
    foreach (db()->query("SELECT kb_id, title, content, tags FROM knowledge_base WHERE status = 'active'")->fetchAll() as $r) {
        $docs[] = ['type' => 'kb', 'id' => (int)$r['kb_id'], 'title' => $r['title'], 'text' => $r['content'], 'url' => null,
                   'index' => $r['title'] . ' ' . $r['title'] . ' ' . $r['tags'] . ' ' . $r['content']];
    }
    $sql = "SELECT p.product_id, p.name, p.description, p.base_price, p.gender, c.name AS cat
              FROM product p JOIN category c ON c.category_id = p.category_id
             WHERE p.status = 'active' AND c.status = 'active'";
    foreach (db()->query($sql)->fetchAll() as $r) {
        $gl = GENDERS[$r['gender']] ?? '';
        $docs[] = ['type' => 'product', 'id' => (int)$r['product_id'], 'title' => $r['name'],
                   'text' => $r['name'] . ' (' . $r['cat'] . ', ' . $gl . ') giá ' . format_vnd($r['base_price']) . '. ' . ($r['description'] ?? ''),
                   'url' => 'index.php?page=product&id=' . (int)$r['product_id'],
                   'index' => $r['name'] . ' ' . $r['name'] . ' ' . $r['cat'] . ' ' . $gl . ' ' . ($r['description'] ?? '')];
    }
    return $docs;
}

// Xếp hạng bằng BM25 (k1 = 1.5, b = 0.75). Trả về tối đa $k tài liệu có điểm > 0, điểm cao trước.
function rag_retrieve(string $query, int $k = 3): array
{
    $qt = array_values(array_unique(rag_tokens($query)));
    $docs = rag_documents();
    $N = count($docs);
    if (!$qt || $N === 0) {
        return [];
    }
    $tf = [];
    $len = [];
    $df = [];
    foreach ($docs as $i => $d) {
        $t = rag_tokens($d['index']);
        $len[$i] = count($t);
        $tf[$i] = array_count_values($t);
        foreach (array_keys($tf[$i]) as $w) {
            $df[$w] = ($df[$w] ?? 0) + 1;
        }
    }
    $avg = max(1.0, array_sum($len) / $N);
    $scores = [];
    foreach ($docs as $i => $d) {
        $score = 0.0;
        foreach ($qt as $w) {
            if (!isset($tf[$i][$w])) continue;
            $idf = log(1 + ($N - $df[$w] + 0.5) / ($df[$w] + 0.5));
            $f = $tf[$i][$w];
            $score += $idf * ($f * 2.5) / ($f + 1.5 * (0.25 + 0.75 * $len[$i] / $avg));
        }
        if ($score > 0) $scores[$i] = $score;
    }
    arsort($scores);
    $out = [];
    foreach (array_slice($scores, 0, $k, true) as $i => $sc) {
        $out[] = $docs[$i] + ['score' => $sc];
    }
    return $out;
}

/* ---------------- Tư vấn size ---------------- */
// Đọc chiều dài bàn chân (cm) từ câu hỏi: "chân dài 26cm", "260mm", "26.5 cm"...
function parse_foot_length(string $text): ?float
{
    $n = vn_normalize($text);
    $v = null;
    if (preg_match('/(\d{2}(?:[.,]\d)?)\s*(?:cm|centimet)/', $n, $m)) {
        $v = (float)str_replace(',', '.', $m[1]);
    } elseif (preg_match('/(\d{3})\s*mm/', $n, $m)) {
        $v = ((float)$m[1]) / 10;
    } elseif (preg_match('/(?:chan|ban chan)[^0-9]{0,15}(\d{2}(?:[.,]\d)?)(?!\d)/', $n, $m)) {
        $v = (float)str_replace(',', '.', $m[1]);
    }
    return ($v !== null && $v >= 18 && $v <= 35) ? $v : null;
}

// Chọn size EU nhỏ nhất có chiều dài lòng giày >= chiều dài chân + 0.5 cm (+0.5 cm nếu chân bè/hay mang tất dày)
function size_recommend(float $foot, bool $wide = false): ?array
{
    $rows = db()->query('SELECT eu_size, foot_length_cm FROM size_chart ORDER BY eu_size')->fetchAll();
    if (!$rows) {
        return null;
    }
    $target = $foot + 0.5 + ($wide ? 0.5 : 0);
    $pick = null;
    foreach ($rows as $r) {
        if ((float)$r['foot_length_cm'] >= $target) {
            $pick = $r;
            break;
        }
    }
    $over = ($pick === null);
    if ($over) {
        $pick = end($rows);
    }
    return ['eu' => (int)$pick['eu_size'], 'inner_cm' => (float)$pick['foot_length_cm'], 'wide' => $wide, 'out_of_range' => $over];
}
