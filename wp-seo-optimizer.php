<?php
/**
 * Plugin Name: WordPress SEO ve İçerik Optimizasyonu
 * Description: İçeriklerinizi otomatik analiz eder ve SEO önerileri sunar
 * Version: 1.0.0
 * Author: M. Serhat Aksel
 * License: GPL v2 or later
 */

defined('ABSPATH') || exit;

class WP_SEO_Content_Optimizer {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('add_meta_boxes', [$this, 'add_seo_meta_box']);
        add_action('save_post', [$this, 'save_seo_analysis']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_filter('the_content', [$this, 'optimize_content'], 999);
    }

    // SEO Meta Box ekleme
    public function add_seo_meta_box() {
        add_meta_box(
            'seo_analyzer',
            'SEO Analizi',
            [$this, 'render_meta_box'],
            'post',
            'normal',
            'high'
        );
    }

    // Meta Box içeriği
    public function render_meta_box($post) {
        $seo_score = $this->analyze_content($post->post_content);
        $recommendations = $this->get_seo_recommendations($post);
        ?>
        <div class="seo-analysis-wrapper">
            <div class="seo-score">
                <h3>SEO Puanı: <?php echo $seo_score; ?>/100</h3>
            </div>
            
            <div class="seo-recommendations">
                <h4>SEO Önerileri:</h4>
                <ul>
                    <?php foreach ($recommendations as $rec): ?>
                        <li><?php echo esc_html($rec); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php
    }

    // İçerik analizi
    private function analyze_content($content) {
        $score = 100;
        $factors = [
            'word_count' => $this->get_word_count($content),
            'keyword_density' => $this->calculate_keyword_density($content),
            'heading_structure' => $this->analyze_headings($content),
            'internal_links' => $this->count_internal_links($content),
            'readability' => $this->check_readability($content)
        ];
        
        // Kelime sayısı kontrolü
        if ($factors['word_count'] < 300) {
            $score -= 20;
        }
        
        // Anahtar kelime yoğunluğu kontrolü
        if ($factors['keyword_density'] < 1 || $factors['keyword_density'] > 3) {
            $score -= 15;
        }
        
        // Başlık yapısı kontrolü
        if (!$factors['heading_structure']) {
            $score -= 15;
        }
        
        return max(0, $score);
    }

    // Kelime sayısı hesaplama
    private function get_word_count($content) {
        return str_word_count(strip_tags($content));
    }

    // Anahtar kelime yoğunluğu hesaplama
    private function calculate_keyword_density($content) {
        $keyword = get_post_meta(get_the_ID(), '_yoast_wpseo_focuskw', true);
        if (empty($keyword)) return 0;
        
        $content = strtolower(strip_tags($content));
        $keyword_count = substr_count($content, strtolower($keyword));
        $total_words = str_word_count($content);
        
        return ($keyword_count / $total_words) * 100;
    }

    // Başlık yapısı analizi
    private function analyze_headings($content) {
        return preg_match('/<h[1-6].*?>.*?<\/h[1-6]>/i', $content);
    }

    // İç link sayısı
    private function count_internal_links($content) {
        preg_match_all('/<a href="' . get_site_url() . '.*?".*?>/i', $content, $matches);
        return count($matches[0]);
    }

    // Okunabilirlik kontrolü
    private function check_readability($content) {
        $sentences = preg_split('/[.!?]/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $total_words = 0;
        $total_sentences = count($sentences);
        
        foreach ($sentences as $sentence) {
            $total_words += str_word_count($sentence);
        }
        
        return $total_words / $total_sentences;
    }

    // SEO önerileri oluşturma
    private function get_seo_recommendations($post) {
        $recommendations = [];
        $content = $post->post_content;
        
        if ($this->get_word_count($content) < 300) {
            $recommendations[] = 'İçerik en az 300 kelime olmalıdır.';
        }
        
        if (!$this->analyze_headings($content)) {
            $recommendations[] = 'İçerikte alt başlıklar (H2, H3) kullanın.';
        }
        
        if ($this->count_internal_links($content) < 2) {
            $recommendations[] = 'En az 2 iç link eklemeyi düşünün.';
        }
        
        if ($this->check_readability($content) > 20) {
            $recommendations[] = 'Cümlelerinizi daha kısa tutun.';
        }
        
        return $recommendations;
    }

    // İçerik optimizasyonu
    public function optimize_content($content) {
        if (!is_singular() || !in_the_loop()) {
            return $content;
        }

        // Otomatik alt başlık ekleme
        if (!$this->analyze_headings($content)) {
            $paragraphs = explode("\n\n", $content);
            if (count($paragraphs) > 2) {
                $paragraphs[1] = '<h2>'. $this->generate_heading($paragraphs[1]) .'</h2>' . $paragraphs[1];
            }
            $content = implode("\n\n", $paragraphs);
        }

        // Otomatik içerik yapılandırma
        $content = $this->add_paragraph_spacing($content);
        $content = $this->optimize_images($content);
        
        return $content;
    }

    // Başlık oluşturma
    private function generate_heading($text) {
        $words = str_word_count(strip_tags($text), 1);
        return implode(' ', array_slice($words, 0, 5)) . '...';
    }

    // Paragraf aralıkları düzenleme
    private function add_paragraph_spacing($content) {
        return preg_replace('/<\/p><p>/', "</p>\n<p>", $content);
    }

    // Görsel optimizasyonu
    private function optimize_images($content) {
        return preg_replace_callback('/<img[^>]+>/', function($matches) {
            $img = $matches[0];
            if (!strpos($img, 'alt=')) {
                $img = str_replace('<img', '<img alt=""', $img);
            }
            if (!strpos($img, 'loading=')) {
                $img = str_replace('<img', '<img loading="lazy"', $img);
            }
            return $img;
        }, $content);
    }

    // Admin menüsü
    public function add_admin_menu() {
        add_menu_page(
            'SEO Optimizasyonu',
            'SEO Optimizer',
            'manage_options',
            'seo-optimizer',
            [$this, 'render_admin_page'],
            'dashicons-chart-line'
        );
    }

    // Admin sayfası
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>SEO ve İçerik Optimizasyonu</h1>
            <div class="seo-dashboard">
                <h2>Site Geneli SEO Analizi</h2>
                <?php $this->display_site_analysis(); ?>
                
                <h2>Son İçerikler Analizi</h2>
                <?php $this->display_recent_posts_analysis(); ?>
            </div>
        </div>
        <?php
    }

    // Site analizi gösterimi
    private function display_site_analysis() {
        $total_posts = wp_count_posts()->publish;
        $optimized_posts = 0;
        $total_score = 0;
        
        $recent_posts = get_posts(['numberposts' => -1]);
        foreach ($recent_posts as $post) {
            $score = $this->analyze_content($post->post_content);
            $total_score += $score;
            if ($score > 80) $optimized_posts++;
        }
        
        ?>
        <div class="site-stats">
            <p>Toplam İçerik: <?php echo $total_posts; ?></p>
            <p>Optimize İçerik: <?php echo $optimized_posts; ?></p>
            <p>Ortalama SEO Puanı: <?php echo round($total_score / count($recent_posts), 2); ?></p>
        </div>
        <?php
    }

    // Son içerikler analizi
    private function display_recent_posts_analysis() {
        $recent_posts = get_posts(['numberposts' => 5]);
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Başlık</th>
                    <th>SEO Puanı</th>
                    <th>Kelime Sayısı</th>
                    <th>Son Güncelleme</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_posts as $post): ?>
                    <tr>
                        <td><?php echo esc_html($post->post_title); ?></td>
                        <td><?php echo $this->analyze_content($post->post_content); ?></td>
                        <td><?php echo $this->get_word_count($post->post_content); ?></td>
                        <td><?php echo get_the_modified_date('', $post->ID); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    // Admin scriptleri
    public function enqueue_admin_scripts($hook) {
        if ('post.php' != $hook && 'post-new.php' != $hook) {
            return;
        }
        wp_enqueue_script('seo-analyzer', plugins_url('js/analyzer.js', __FILE__), ['jquery'], '1.0.0', true);
        wp_enqueue_style('seo-analyzer', plugins_url('css/analyzer.css', __FILE__));
    }
}

// Eklentiyi başlat
WP_SEO_Content_Optimizer::get_instance();
