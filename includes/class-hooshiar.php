<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Hooshiar {
    private $option_key = 'hooshiar_settings';
    private $defaults;

    public function __construct() {
        $this->defaults = [
            'greeting'   => 'سلام! 👋 من هوشیارم — دستیار هوشمند فروشگاه. هر سوالی داری بپرس: قیمت، سایز، موجودی، تخفیف، ارسال... 😊',
            'position'   => 'left',
            'bg_color'   => '#6C4FF0',
        ];

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'wp_footer',          [ $this, 'render_widget' ] );
        add_action( 'wp_ajax_hooshiar_chat',     [ $this, 'handle_chat' ] );
        add_action( 'wp_ajax_nopriv_hooshiar_chat', [ $this, 'handle_chat' ] );
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function enqueue() {
        wp_enqueue_style( 'hooshiar-css', HOOSHIAR_URL . 'assets/css/hooshiar.css', [], HOOSHIAR_VERSION );
        wp_enqueue_script( 'hooshiar-js', HOOSHIAR_URL . 'assets/js/hooshiar.js', [], HOOSHIAR_VERSION, true );
        wp_localize_script( 'hooshiar-js', 'hooshiarData', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'hooshiar_nonce' ),
            'greeting' => $this->get_setting( 'greeting' ),
            'position' => $this->get_setting( 'position' ),
            'bgColor'  => $this->get_setting( 'bg_color' ),
        ] );
    }

    public function render_widget() {
        $s = [
            'btn_color' => esc_attr( $this->get_setting( 'bg_color' ) ),
            'position'  => esc_attr( $this->get_setting( 'position' ) ),
        ];
        ?>
        <div id="hooshiar-widget" class="hooshiar-<?php echo $s['position']; ?>" style="--hooshiar-color:<?php echo $s['btn_color']; ?>">
            <button id="hooshiar-toggle" class="hooshiar-toggle" aria-label="باز کردن چت">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
            </button>
            <div id="hooshiar-box" class="hooshiar-box" style="display:none">
                <div class="hooshiar-header">
                    <span>🤖 هوشیار</span>
                    <button id="hooshiar-close" class="hooshiar-header-btn" title="بستن">✕</button>
                </div>
                <div id="hooshiar-messages" class="hooshiar-messages"></div>
                <div class="hooshiar-input-area">
                    <input type="text" id="hooshiar-input" placeholder="سوالت رو بنویس..." autocomplete="off" dir="auto" />
                    <button id="hooshiar-send" title="ارسال">➤</button>
                </div>
            </div>
        </div>
        <?php
    }

    public function handle_chat() {
        check_ajax_referer( 'hooshiar_nonce', 'nonce' );
        
        $q = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
        if ( empty( $q ) ) wp_send_json_error( [ 'text' => 'پیام خالی است.' ] );

        // 1. Check greetings
        $greeting = $this->check_greetings( $q );
        if ( $greeting ) { wp_send_json_success( [ 'text' => $greeting ] ); return; }

        // 2. Check knowledge base
        $kb = $this->check_kb( $q );
        if ( $kb ) { wp_send_json_success( [ 'text' => $kb ] ); return; }

        // 3. Search products
        $products = $this->search_products( $q );
        if ( $products ) { wp_send_json_success( [ 'text' => $products ] ); return; }

        // 4. Try DuckDuckGo AI
        $ai = $this->try_ai( $q );
        if ( $ai ) { wp_send_json_success( [ 'text' => $ai ] ); return; }

        // 5. Final fallback — always helpful
        wp_send_json_success( [ 'text' => $this->final_fallback( $q ) ] );
    }

    private function check_greetings( $q ) {
        $q = $this->norm( $q );
        $patterns = [
            'سلام' => 'سلام عزیزم! 👋 خوبی؟ چطور میتونم کمکت کنم؟ میتونی درباره محصولات، قیمت، سایز، تخفیف یا ارسال سوال کنی! 😊',
            'خوبی' => 'مرسی که پرسیدی! 😊 من همیشه اینجام تا کمکت کنم. چی نیاز داری؟',
            'چطوری' => 'سلام! خوبم ممنون 😊 تو چطوری؟ چی میتونم برات پیدا کنم؟',
            'کی هستی' => 'من هوشیارم — دستیار فروشگاه! 🛍️ میتونم محصولات رو برات پیدا کنم، قیمت و موجودی رو بگم، درباره تخفیف و ارسال راهنماییت کنم. بگو چی میخوای!',
            'چه کار' => 'کلی کار میتونم برات بکنم! 🎯 محصولات فروشگاه رو جستجو کنم، قیمت و سایز بگم، راهنمای خرید باشم، درباره تخفیف و ارسال توضیح بدم. بگو دنبال چی میگردی؟',
            'ممنون' => 'خواهش میکنم! 😊 هر وقت سوالی داشتی من اینجام.',
            'خداحافظ' => 'خدانگهدار! 👋 هر وقت برگشتی من اینجام. روز خوبی داشته باشی! 😊',
        ];
        foreach ( $patterns as $key => $val ) {
            if ( mb_strpos( $q, $key, 0, 'UTF-8' ) !== false ) return $val;
        }
        return false;
    }

    private function check_kb( $q ) {
        $q = $this->norm( $q );
        $kb = [
            'ارسال رایگان' => '🚚 ارسال برای خریدهای بالای ۲ مللیون تومان کاملاً رایگانه! زیر ۲ مللیون هزینه بر اساس شهر و وزن محاسبه میشه. تهران ۱-۲ روز، شهرستان ۲-۴ روز کاری.',
            'هزینه ارسال' => '🚚 ارسال رایگان بالای ۲ میلیون تومان! زیر ۲ میلیون: هزینه بر اساس وزن و شهر. تهران ۱-۲ روز کاری، شهرستان ۲-۴ روز. بسته‌بندی مقاوم + کد رهگیری 😊',
            'مرجوعی|بازگشت|تعویض' => '🔄 ۷ روز مهلت بازگشت داری! لباس شسته نشده و با تگ اصلی باشه. لباس زیر فقط با ایراد تولیدی. هزینه برگشت: ایراد کالا با ما، انصراف با شما. وجه ۲۴-۴۸ ساعته برمیگرده.',
            'پرداخت' => '💳 پرداخت آنلاین با همه کارت‌های بانکی + پرداخت در محل برای بعضی مناطق. کاملاً امن!',
            'تخفیف|کد تخفیف|حراج' => '🎉 همیشه یه بهونه داریم: جشنواره آخر فصل (اسفند و شهریور) تا ۵۰٪، بلک فرایدی، تخفیف مناسبتی! کد تخفیف رو توی صفحه تسویه حساب وارد کن. کد تخفیف اولین خرید هم داریم! 😊',
            'سایز|اندازه' => '📏 دور سینه، کمر و باسن رو با متر بگیر و با جدول سایز هر محصول مقایسه کن. بین دو سایز: لباس آزاد = بزرگتر، لباس جذب = کوچکتر. برندهای ترک یه سایز بزرگترن. بگو چه محصولی مدنظره دقیقتر بگم 😊',
            'شستشو|نگهداری|شستن' => '🧼 بلوز و شومیز: آب سرد، اتو کم. مانتو و کت: خشکشویی. جین: پشت و رو بشور، آب سرد، خشک‌کن ممنوع. لباس مجلسی: فقط خشکشویی! وایتکس ممنوع!',
            'اصالت|کیفیت|اورجینال' => '💯 تمام محصولات اورجینال با ضمانت اصالت از تولیدی‌های معتبر. بازرسی کیفی قبل از ارسال. عدم تطابق = ۱۰۰٪ بازگشت وجه!',
            'پیگیری|سفارش من' => '📦 با شماره سفارش میتونی از طریق سایت پیگیری کنی. کد رهگیری بعد از ارسال پیامک میشه. هر سوالی داشتی بپرس!',
            'بسته بندی' => '📦 بسته‌بندی مقاوم چندلایه، داخل نایلون محافظ، ضد رطوبت، بدون درج نام فروشگاه. لباس‌ها اتوکشیده تحویلت میدیم! 👌',
            'ارسال شهرستان' => '🚛 تهران و کرج ۱-۲ روزٌ مراکز استان ۲-۳ روز، سایر شهرها ۳-۴ روز کاری. خرید بالای ۲ میلیون = ارسال رایگان!',
            'مانتو' => '👘 مانتوهای ما: اداری (رسمی، رنگ خنثی)، مجلسی (گیپور و سنگ‌دوزی)، اسپرت (جین و کتان)، پالتو (پاییزه-زمستانى)، بارانی (سبک و ضدآب). محصولات موجود رو میتونی تو سایت ببینی — بگو چه رنگی میخوای تا دقیقتر بگردم! 😊',
            'شومیز|بلوز' => '👚 شومیز و بلوز: یقه‌ای رسمی، طرح‌دار مهمونی، ساده روزمره، حریر-ساتن مجلسی، نخی تابستونی. رنگ و مدل مورد علاقه‌ات رو بگو!',
            'شلوار|جین' => '👖 شلوارها: جین (مادر، اسکینی، بگ، راسته، دمپا)، پارچه‌ای (رسمی، کتان)، لگ و اسلش (راحتی)، شلوارک (تابستونی). سایزت رو بگو!',
            'کیف' => '👜 کیف دستی (مجلسی)، دوشی (روزمره)، کوله‌پشتی (اسپرت)، کلاچ (مهمانی). مشکی، کرم و قهوه‌ای پرفروش‌ترین‌ها!',
            'کفش' => '👠 کفش: پاشنه‌بلند (مجلسی)، تخت و باله (روزمره)، اسنیکرز (اسپرت)، بوت (پاییزه)، صندل (تابستونی). سایز ۳۶ تا ۴۱.',
            'روسری|شال' => '🧣 روسری و شال: حریر لطیف (مجلسی)، ساتن براق، طرح‌دار و گل‌گلی، ساده. با هر مانتویی ست میشن!',
            'ست|ست کردن' => '✨ ست کردن: روزمره = تیشرت سفید + جین + کتانی 👟 رسمی = شومیز یقه‌ای + شلوار پارچه‌ای 👔 مهمونی = مانتو مجلسی + کیف دستی 👜 رنگ‌های خنثی (سفید-مشکی-کرم-طوسی) پایه کمدت باشن!',
            'تماس|پشتیبانیساعت کاری'=> '📞 پوتتیبانی هم؇ روزه ۹ صبح تا ۹ شب. خارج از ساعت هم میتونی پیام براری — در اولین فرصت جواب میدیم!',
            'خرید حضوری' => '🏪 فروش فقط آنلاینه ولی: عکس و توضیحات دقیق هر محصول، چت آنلاین، ۷ روز ضمانت بازگشت، تعویض رایگان سایز! خیالت راحت 😊',
        ];

        $best_score = 0; $best_answer = false;
        foreach ( $kb as $keys => $answer ) {
            $score = 0;
            foreach ( explode( '|', $keys ) as $key ) {
                if ( mb_strpos( $q, $key, 0, 'UTF-8' ) !== false ) $score += 3;
                // Also check partial matches
                $parts = explode( ' ', $key );
                foreach ( $parts as $part ) {
                    if ( mb_strlen( $part, 'UTF-8' ) > 2 && mb_strpos( $q, $part, 0, 'UTF-8' ) !== false ) $score += 1;
                }
            }
            if ( $score > $best_score ) { $best_score = $score; $best_answer = $answer; }
        }
        return $best_score >= 2 ? $best_answer : false;
    }

    private function search_products( $q ) {
        if ( ! class_exists( 'WooCommerce' ) ) return false;

        $q = $this->norm( $q );
        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 4,
            's'              => $q,
            'orderby'        => 'relevance',
        ];

        $query = new WP_Query( $args );
        if ( ! $query->have_posts() ) { wp_reset_postdata(); return false; }

        $result = "🔍 این محصولات رو بر اساس «{$q}» پیدا کردم:\n\n";
        $i = 1;
        while ( $query->have_posts() ) {
            $query->the_post();
            $product = wc_get_product( get_the_ID() );
            if ( ! $product ) continue;
            
            $price = $product->get_price_html();
            $price = wp_strip_all_tags( $price );
            $link  = get_permalink();
            $title = get_the_title();
            
            $result .= "{$i}. **{$title}**\n";
            $result .= "   💰 {$price}\n";
            
            if ( $product->is_in_stock() ) {
                $result .= "   ✅ موجود در انبار\n";
            } else {
                $result .= "   ❌ ناموجود\n";
            }
            
            $result .= "   🔗 {$link}\n\n";
            $i++;
        }
        wp_reset_postdata();

        if ( $i === 1 ) return false;
        $result .= "💡 روی لینک‌ها کلیک کن تا جزئیات رو ببینی! سوال دیگه‌ای داری؟ 😊";
        return $result;
    }

    private function try_ai( $q ) {
        // Skip — DuckDuckGo blocked in Iran, no other free API available
        return false;
    }

    private function final_fallback( $q ) {
        $q = $this->norm( $q );
        
        // Check if it seems like a product search
        $product_keywords = [ 'قیمت', 'قیمتش', 'تومان', 'تومن', 'موجود', 'موجودی', 'دارید', 'دارین', 'بخر', 'خرید', 
                             'مانتو', 'شومیز', 'بلوز', 'شلوار', 'جین', 'کیف', 'کفش', 'روسری', 'شال', 'تیشرت',
                             'سارافون', 'دامن', 'کت', 'پالتو', 'تاپ', 'لگ', 'اکسسوری', 'پیراهن' ];

        foreach ( $product_keywords as $kw ) {
            if ( mb_strpos( $q, $kw, 0, 'UTF-8' ) !== false ) {
                $products = $this->search_products( $q );
                if ( $products ) return $products;
                return "متأسفانه نتونستم محصولی با عنوان «{$q}» پیدا کنم 😅 میتونی با کلمات دیگه‌ای امتحان کنی، یا یه دسته‌بندی (مثلاً «مانتو» یا «شلوار») رو بگی تا همه محصولاتش رو نشونت بدم. 😊";
            }
        }

        return "سلام! 😊 سوالت رو متوجه شدم. من میتونم تو این موارد کمکت کنم:\n\n🔍 **محصولات:** هر محصولی میخوای بگو (مثلاً «مانتو» یا «شلوار جین») تا برات پیدا کنم\n💰 **قیمت و موجودی:** بگو چه محصولی میخوای\n📏 **سایز و راهنما:** درباره سایز، جنس پارچه، نگهداری\n🚚 **ارسال:** رایگان بالای ۲ مللیون\n🔄 **مرجوعی:** ۷ روز مهلت\n🎉 **تخفیف:** جشنواره‌های فصلی\n\nبگو دقیقاً دنبال چی میگردی تا بهتر کمکت کنم! 😊";
    }

    private function norm( $text ) {
        $text = mb_strtolower( $text, 'UTF-8' );
        $text = str_replace( [ 'آ', 'ي', 'ك', 'ة' ], [ 'ا', 'ی', 'ک', 'ه' ], $text );
        return trim( $text );
    }

    private function get_setting( $key ) {
        $opts = get_option( $this->option_key, [] );
        return isset( $opts[ $key ] ) ? $opts[ $key ] : ( $this->defaults[ $key ] ?? '' );
    }

    // Admin
    public function admin_menu() {
        add_options_page( 'تنظیمات هوشیار', '🤖 هوشیار', 'manage_options', 'hooshiar', [ $this, 'admin_page' ] );
    }

    public function register_settings() {
        register_setting( 'hooshiar_settings', $this->option_key );
    }

    public function admin_page() {
        $s = get_option( $this->option_key, $this->defaults );
        ?>
        <div class="wrap" dir="rtl">
            <h1>🤖 تنظیمات هوشیار</h1>
            <p style="color:#666">دستیار هوشمند فروشگاه — جستجوی محصولات و پاسخگویی خودکار ✨</p>
            <form method="post" action="options.php">
                <?php settings_fields( 'hooshiar_settings' ); ?>
                <table class="form-table">
                    <tr>
                        <th>👋 پیام خوش‌آمدگویی</th>
                        <td><textarea name="<?php echo $this->option_key; ?>[greeting]" rows="3" style="width:100%;direction:rtl"><?php echo esc_textarea( $s['greeting'] ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th>📍 موقعیت ویجت</th>
                        <td>
                            <select name="<?php echo $this->option_key; ?>[position]">
                                <option value="left" <?php selected( $s['position'], 'left' ); ?>>چپ</option>
                                <option value="right" <?php selected( $s['position'], 'right' ); ?>>راست</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>🎨 رنگ دکمه</th>
                        <td><input type="color" name="<?php echo $this->option_key; ?>[bg_color]" value="<?php echo esc_attr( $s['bg_color'] ); ?>" /></td>
                    </tr>
                </table>
                <?php submit_button( '💾 ذخیره' ); ?>
            </form>
            <hr />
            <h3>✨ قابلیت‌ها</h3>
            <ul style="list-style:disc;margin-right:20px">
                <li>🔍 <strong>جستجوی محصولات ووکامرس</strong> — اسم محصول رو بگو، قیمت و موجودی رو نشون میده</li>
                <li>📚 <strong>دانشنامه فروشگاه</strong> — ارسال، مرجوعی، سایز، تخفیف، شستشو و...</li>
                <li>💬 <strong>چت آنلاین</strong> — کاملاً فارسی و راست‌چین</li>
                <li>🆓 <strong>کاملاً رایگان</strong> — بدون API Key، فقط ووکامرس نیاز داره</li>
            </ul>
        </div>
        <?php
    }
}
