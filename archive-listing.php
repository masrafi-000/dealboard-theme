<?php get_header(); ?>

<?php
$queried    = get_queried_object();
$is_cat     = ($queried instanceof WP_Term);
$page_title = $is_cat ? $queried->name : 'All Listings';
$found      = $wp_query->found_posts ?? 0;

$active_cat_id    = 0;
$active_parent_id = 0;
$is_subcategory   = false;

if ($is_cat) {
    $active_cat_id = $queried->term_id;
    if ($queried->parent > 0) {
        $is_subcategory   = true;
        $active_parent_id = $queried->parent;
    } else {
        $active_parent_id = $queried->term_id;
    }
}

$all_cats = get_terms([
    'taxonomy'   => 'listing_category',
    'hide_empty' => false,
    'parent'     => 0,
    'orderby'    => 'name',
    'order'      => 'ASC',
]);

$subcats = [];
if ($active_parent_id) {
    $subcats = get_terms([
        'taxonomy'   => 'listing_category',
        'hide_empty' => false,
        'parent'     => $active_parent_id,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ]);
}

$active_country = sanitize_text_field($_GET['country'] ?? '');
$active_sort    = sanitize_text_field($_GET['sort'] ?? 'newest');

// Full world countries list
$country_names = [
  'af'=>['name'=>'Afghanistan','flag'=>'🇦🇫'],
  'al'=>['name'=>'Albania','flag'=>'🇦🇱'],
  'dz'=>['name'=>'Algeria','flag'=>'🇩🇿'],
  'ad'=>['name'=>'Andorra','flag'=>'🇦🇩'],
  'ao'=>['name'=>'Angola','flag'=>'🇦🇴'],
  'ag'=>['name'=>'Antigua and Barbuda','flag'=>'🇦🇬'],
  'ar'=>['name'=>'Argentina','flag'=>'🇦🇷'],
  'am'=>['name'=>'Armenia','flag'=>'🇦🇲'],
  'au'=>['name'=>'Australia','flag'=>'🇦🇺'],
  'at'=>['name'=>'Austria','flag'=>'🇦🇹'],
  'az'=>['name'=>'Azerbaijan','flag'=>'🇦🇿'],
  'bs'=>['name'=>'Bahamas','flag'=>'🇧🇸'],
  'bh'=>['name'=>'Bahrain','flag'=>'🇧🇭'],
  'bd'=>['name'=>'Bangladesh','flag'=>'🇧🇩'],
  'bb'=>['name'=>'Barbados','flag'=>'🇧🇧'],
  'by'=>['name'=>'Belarus','flag'=>'🇧🇾'],
  'be'=>['name'=>'Belgium','flag'=>'🇧🇪'],
  'bz'=>['name'=>'Belize','flag'=>'🇧🇿'],
  'bj'=>['name'=>'Benin','flag'=>'🇧🇯'],
  'bt'=>['name'=>'Bhutan','flag'=>'🇧🇹'],
  'bo'=>['name'=>'Bolivia','flag'=>'🇧🇴'],
  'ba'=>['name'=>'Bosnia and Herzegovina','flag'=>'🇧🇦'],
  'bw'=>['name'=>'Botswana','flag'=>'🇧🇼'],
  'br'=>['name'=>'Brazil','flag'=>'🇧🇷'],
  'bn'=>['name'=>'Brunei','flag'=>'🇧🇳'],
  'bg'=>['name'=>'Bulgaria','flag'=>'🇧🇬'],
  'bf'=>['name'=>'Burkina Faso','flag'=>'🇧🇫'],
  'bi'=>['name'=>'Burundi','flag'=>'🇧🇮'],
  'cv'=>['name'=>'Cape Verde','flag'=>'🇨🇻'],
  'kh'=>['name'=>'Cambodia','flag'=>'🇰🇭'],
  'cm'=>['name'=>'Cameroon','flag'=>'🇨🇲'],
  'ca'=>['name'=>'Canada','flag'=>'🇨🇦'],
  'cf'=>['name'=>'Central African Republic','flag'=>'🇨🇫'],
  'td'=>['name'=>'Chad','flag'=>'🇹🇩'],
  'cl'=>['name'=>'Chile','flag'=>'🇨🇱'],
  'cn'=>['name'=>'China','flag'=>'🇨🇳'],
  'co'=>['name'=>'Colombia','flag'=>'🇨🇴'],
  'km'=>['name'=>'Comoros','flag'=>'🇰🇲'],
  'cg'=>['name'=>'Congo','flag'=>'🇨🇬'],
  'cr'=>['name'=>'Costa Rica','flag'=>'🇨🇷'],
  'hr'=>['name'=>'Croatia','flag'=>'🇭🇷'],
  'cu'=>['name'=>'Cuba','flag'=>'🇨🇺'],
  'cy'=>['name'=>'Cyprus','flag'=>'🇨🇾'],
  'cz'=>['name'=>'Czech Republic','flag'=>'🇨🇿'],
  'dk'=>['name'=>'Denmark','flag'=>'🇩🇰'],
  'dj'=>['name'=>'Djibouti','flag'=>'🇩🇯'],
  'dm'=>['name'=>'Dominica','flag'=>'🇩🇲'],
  'do'=>['name'=>'Dominican Republic','flag'=>'🇩🇴'],
  'ec'=>['name'=>'Ecuador','flag'=>'🇪🇨'],
  'eg'=>['name'=>'Egypt','flag'=>'🇪🇬'],
  'sv'=>['name'=>'El Salvador','flag'=>'🇸🇻'],
  'gq'=>['name'=>'Equatorial Guinea','flag'=>'🇬🇶'],
  'er'=>['name'=>'Eritrea','flag'=>'🇪🇷'],
  'ee'=>['name'=>'Estonia','flag'=>'🇪🇪'],
  'sz'=>['name'=>'Eswatini','flag'=>'🇸🇿'],
  'et'=>['name'=>'Ethiopia','flag'=>'🇪🇹'],
  'fj'=>['name'=>'Fiji','flag'=>'🇫🇯'],
  'fi'=>['name'=>'Finland','flag'=>'🇫🇮'],
  'fr'=>['name'=>'France','flag'=>'🇫🇷'],
  'ga'=>['name'=>'Gabon','flag'=>'🇬🇦'],
  'gm'=>['name'=>'Gambia','flag'=>'🇬🇲'],
  'ge'=>['name'=>'Georgia','flag'=>'🇬🇪'],
  'de'=>['name'=>'Germany','flag'=>'🇩🇪'],
  'gh'=>['name'=>'Ghana','flag'=>'🇬🇭'],
  'gr'=>['name'=>'Greece','flag'=>'🇬🇷'],
  'gd'=>['name'=>'Grenada','flag'=>'🇬🇩'],
  'gt'=>['name'=>'Guatemala','flag'=>'🇬🇹'],
  'gn'=>['name'=>'Guinea','flag'=>'🇬🇳'],
  'gw'=>['name'=>'Guinea-Bissau','flag'=>'🇬🇼'],
  'gy'=>['name'=>'Guyana','flag'=>'🇬🇾'],
  'ht'=>['name'=>'Haiti','flag'=>'🇭🇹'],
  'hn'=>['name'=>'Honduras','flag'=>'🇭🇳'],
  'hu'=>['name'=>'Hungary','flag'=>'🇭🇺'],
  'is'=>['name'=>'Iceland','flag'=>'🇮🇸'],
  'in'=>['name'=>'India','flag'=>'🇮🇳'],
  'id'=>['name'=>'Indonesia','flag'=>'🇮🇩'],
  'ir'=>['name'=>'Iran','flag'=>'🇮🇷'],
  'iq'=>['name'=>'Iraq','flag'=>'🇮🇶'],
  'ie'=>['name'=>'Ireland','flag'=>'🇮🇪'],
  'il'=>['name'=>'Israel','flag'=>'🇮🇱'],
  'it'=>['name'=>'Italy','flag'=>'🇮🇹'],
  'jm'=>['name'=>'Jamaica','flag'=>'🇯🇲'],
  'jp'=>['name'=>'Japan','flag'=>'🇯🇵'],
  'jo'=>['name'=>'Jordan','flag'=>'🇯🇴'],
  'kz'=>['name'=>'Kazakhstan','flag'=>'🇰🇿'],
  'ke'=>['name'=>'Kenya','flag'=>'🇰🇪'],
  'ki'=>['name'=>'Kiribati','flag'=>'🇰🇮'],
  'kw'=>['name'=>'Kuwait','flag'=>'🇰🇼'],
  'kg'=>['name'=>'Kyrgyzstan','flag'=>'🇰🇬'],
  'la'=>['name'=>'Laos','flag'=>'🇱🇦'],
  'lv'=>['name'=>'Latvia','flag'=>'🇱🇻'],
  'lb'=>['name'=>'Lebanon','flag'=>'🇱🇧'],
  'ls'=>['name'=>'Lesotho','flag'=>'🇱🇸'],
  'lr'=>['name'=>'Liberia','flag'=>'🇱🇷'],
  'ly'=>['name'=>'Libya','flag'=>'🇱🇾'],
  'li'=>['name'=>'Liechtenstein','flag'=>'🇱🇮'],
  'lt'=>['name'=>'Lithuania','flag'=>'🇱🇹'],
  'lu'=>['name'=>'Luxembourg','flag'=>'🇱🇺'],
  'mg'=>['name'=>'Madagascar','flag'=>'🇲🇬'],
  'mw'=>['name'=>'Malawi','flag'=>'🇲🇼'],
  'my'=>['name'=>'Malaysia','flag'=>'🇲🇾'],
  'mv'=>['name'=>'Maldives','flag'=>'🇲🇻'],
  'ml'=>['name'=>'Mali','flag'=>'🇲🇱'],
  'mt'=>['name'=>'Malta','flag'=>'🇲🇹'],
  'mh'=>['name'=>'Marshall Islands','flag'=>'🇲🇭'],
  'mr'=>['name'=>'Mauritania','flag'=>'🇲🇷'],
  'mu'=>['name'=>'Mauritius','flag'=>'🇲🇺'],
  'mx'=>['name'=>'Mexico','flag'=>'🇲🇽'],
  'fm'=>['name'=>'Micronesia','flag'=>'🇫🇲'],
  'md'=>['name'=>'Moldova','flag'=>'🇲🇩'],
  'mc'=>['name'=>'Monaco','flag'=>'🇲🇨'],
  'mn'=>['name'=>'Mongolia','flag'=>'🇲🇳'],
  'me'=>['name'=>'Montenegro','flag'=>'🇲🇪'],
  'ma'=>['name'=>'Morocco','flag'=>'🇲🇦'],
  'mz'=>['name'=>'Mozambique','flag'=>'🇲🇿'],
  'mm'=>['name'=>'Myanmar','flag'=>'🇲🇲'],
  'na'=>['name'=>'Namibia','flag'=>'🇳🇦'],
  'nr'=>['name'=>'Nauru','flag'=>'🇳🇷'],
  'np'=>['name'=>'Nepal','flag'=>'🇳🇵'],
  'nl'=>['name'=>'Netherlands','flag'=>'🇳🇱'],
  'nz'=>['name'=>'New Zealand','flag'=>'🇳🇿'],
  'ni'=>['name'=>'Nicaragua','flag'=>'🇳🇮'],
  'ne'=>['name'=>'Niger','flag'=>'🇳🇪'],
  'ng'=>['name'=>'Nigeria','flag'=>'🇳🇬'],
  'no'=>['name'=>'Norway','flag'=>'🇳🇴'],
  'om'=>['name'=>'Oman','flag'=>'🇴🇲'],
  'pk'=>['name'=>'Pakistan','flag'=>'🇵🇰'],
  'pw'=>['name'=>'Palau','flag'=>'🇵🇼'],
  'pa'=>['name'=>'Panama','flag'=>'🇵🇦'],
  'pg'=>['name'=>'Papua New Guinea','flag'=>'🇵🇬'],
  'py'=>['name'=>'Paraguay','flag'=>'🇵🇾'],
  'pe'=>['name'=>'Peru','flag'=>'🇵🇪'],
  'ph'=>['name'=>'Philippines','flag'=>'🇵🇭'],
  'pl'=>['name'=>'Poland','flag'=>'🇵🇱'],
  'pt'=>['name'=>'Portugal','flag'=>'🇵🇹'],
  'qa'=>['name'=>'Qatar','flag'=>'🇶🇦'],
  'ro'=>['name'=>'Romania','flag'=>'🇷🇴'],
  'ru'=>['name'=>'Russia','flag'=>'🇷🇺'],
  'rw'=>['name'=>'Rwanda','flag'=>'🇷🇼'],
  'kn'=>['name'=>'Saint Kitts and Nevis','flag'=>'🇰🇳'],
  'lc'=>['name'=>'Saint Lucia','flag'=>'🇱🇨'],
  'vc'=>['name'=>'Saint Vincent','flag'=>'🇻🇨'],
  'ws'=>['name'=>'Samoa','flag'=>'🇼🇸'],
  'sm'=>['name'=>'San Marino','flag'=>'🇸🇲'],
  'st'=>['name'=>'Sao Tome and Principe','flag'=>'🇸🇹'],
  'sa'=>['name'=>'Saudi Arabia','flag'=>'🇸🇦'],
  'sn'=>['name'=>'Senegal','flag'=>'🇸🇳'],
  'rs'=>['name'=>'Serbia','flag'=>'🇷🇸'],
  'sc'=>['name'=>'Seychelles','flag'=>'🇸🇨'],
  'sl'=>['name'=>'Sierra Leone','flag'=>'🇸🇱'],
  'sg'=>['name'=>'Singapore','flag'=>'🇸🇬'],
  'sk'=>['name'=>'Slovakia','flag'=>'🇸🇰'],
  'si'=>['name'=>'Slovenia','flag'=>'🇸🇮'],
  'sb'=>['name'=>'Solomon Islands','flag'=>'🇸🇧'],
  'so'=>['name'=>'Somalia','flag'=>'🇸🇴'],
  'za'=>['name'=>'South Africa','flag'=>'🇿🇦'],
  'ss'=>['name'=>'South Sudan','flag'=>'🇸🇸'],
  'es'=>['name'=>'Spain','flag'=>'🇪🇸'],
  'lk'=>['name'=>'Sri Lanka','flag'=>'🇱🇰'],
  'sd'=>['name'=>'Sudan','flag'=>'🇸🇩'],
  'sr'=>['name'=>'Suriname','flag'=>'🇸🇷'],
  'se'=>['name'=>'Sweden','flag'=>'🇸🇪'],
  'ch'=>['name'=>'Switzerland','flag'=>'🇨🇭'],
  'sy'=>['name'=>'Syria','flag'=>'🇸🇾'],
  'tw'=>['name'=>'Taiwan','flag'=>'🇹🇼'],
  'tj'=>['name'=>'Tajikistan','flag'=>'🇹🇯'],
  'tz'=>['name'=>'Tanzania','flag'=>'🇹🇿'],
  'th'=>['name'=>'Thailand','flag'=>'🇹🇭'],
  'tl'=>['name'=>'Timor-Leste','flag'=>'🇹🇱'],
  'tg'=>['name'=>'Togo','flag'=>'🇹🇬'],
  'to'=>['name'=>'Tonga','flag'=>'🇹🇴'],
  'tt'=>['name'=>'Trinidad and Tobago','flag'=>'🇹🇹'],
  'tn'=>['name'=>'Tunisia','flag'=>'🇹🇳'],
  'tr'=>['name'=>'Turkey','flag'=>'🇹🇷'],
  'tm'=>['name'=>'Turkmenistan','flag'=>'🇹🇲'],
  'tv'=>['name'=>'Tuvalu','flag'=>'🇹🇻'],
  'ug'=>['name'=>'Uganda','flag'=>'🇺🇬'],
  'ua'=>['name'=>'Ukraine','flag'=>'🇺🇦'],
  'ae'=>['name'=>'UAE','flag'=>'🇦🇪'],
  'gb'=>['name'=>'United Kingdom','flag'=>'🇬🇧'],
  'us'=>['name'=>'United States','flag'=>'🇺🇸'],
  'uy'=>['name'=>'Uruguay','flag'=>'🇺🇾'],
  'uz'=>['name'=>'Uzbekistan','flag'=>'🇺🇿'],
  'vu'=>['name'=>'Vanuatu','flag'=>'🇻🇺'],
  've'=>['name'=>'Venezuela','flag'=>'🇻🇪'],
  'vn'=>['name'=>'Vietnam','flag'=>'🇻🇳'],
  'ye'=>['name'=>'Yemen','flag'=>'🇾🇪'],
  'zm'=>['name'=>'Zambia','flag'=>'🇿🇲'],
  'zw'=>['name'=>'Zimbabwe','flag'=>'🇿🇼'],
];

global $wpdb;
$used_countries = $wpdb->get_col("
  SELECT DISTINCT meta_value
  FROM {$wpdb->postmeta}
  WHERE meta_key = 'listing_country'
  AND meta_value != ''
  ORDER BY meta_value ASC
");
?>

<style>
.browse-page { padding: 32px 0 60px; }
.browse-title { font-size: 22px; font-weight: 700; color: #111827; }
.listings-count { font-size: 14px; color: #6B7280; margin-top: 4px; }
.browse-top { margin-bottom: 20px; }
.browse-search-row {
    display: flex; gap: 10px; margin-bottom: 20px; align-items: center;
}
.browse-search-input {
    flex: 1; padding: 10px 16px 10px 40px;
    border: 1.5px solid #E5E7EB; border-radius: 8px;
    font-size: 14px; outline: none; font-family: inherit;
    background: white url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%239CA3AF' stroke-width='2' viewBox='0 0 24 24'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E") no-repeat 12px center;
    transition: border-color 0.15s;
}
.browse-search-input:focus { border-color: #C8102E; }
.filter-btn {
    display: flex; align-items: center; gap: 6px; padding: 10px 16px;
    border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 13px;
    font-weight: 500; color: #374151; background: white; cursor: pointer;
    white-space: nowrap; text-decoration: none; transition: all 0.15s; font-family: inherit;
}
.filter-btn:hover { border-color: #C8102E; color: #C8102E; }
.filter-btn.active { border-color: #C8102E; color: #C8102E; background: #FEE2E2; }

/* Filter dropdowns */
.filter-dropdown-wrap { position: relative; }
.filter-dropdown {
    display: none; position: absolute; top: calc(100% + 8px); right: 0;
    background: white; border: 1.5px solid #E5E7EB; border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,.1); z-index: 9999;
    min-width: 220px; padding: 8px;
    max-height: 320px; overflow-y: auto;
    scrollbar-width: thin;
}
.filter-dropdown.open { display: block; }
.filter-dropdown-title {
    font-size: 11px; font-weight: 700; color: #9CA3AF;
    text-transform: uppercase; letter-spacing: .8px;
    padding: 8px 12px 6px;
}
.filter-country-option {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 12px; border-radius: 8px; cursor: pointer;
    font-size: 13px; color: #374151; text-decoration: none;
    transition: background .15s;
}
.filter-country-option:hover { background: #F9FAFB; }
.filter-country-option.active { background: #FEE2E2; color: #C8102E; font-weight: 600; }
.filter-clear {
    display: block; text-align: center; padding: 8px 12px;
    font-size: 12px; color: #6B7280; border-top: 1px solid #F3F4F6;
    margin-top: 4px; cursor: pointer; text-decoration: none;
}
.filter-clear:hover { color: #C8102E; }

/* Sort option */
.sort-option {
    display: flex; align-items: center; gap: 8px;
    padding: 9px 12px; border-radius: 8px; cursor: pointer;
    font-size: 13px; color: #374151; text-decoration: none;
    transition: background .15s;
}
.sort-option:hover { background: #F9FAFB; }
.sort-option.active { background: #FEE2E2; color: #C8102E; font-weight: 600; }

/* Category tabs */
.cat-tabs-wrapper { margin-bottom: 0; }
.cat-tabs-outer { display: flex; align-items: flex-start; gap: 8px; }
.cat-tab-fixed { flex-shrink: 0; }
.cat-tabs-scroll {
    display: flex; align-items: center; gap: 8px;
    overflow-x: auto; scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin; scrollbar-color: #D1D5DB #F3F4F6;
    padding-bottom: 10px; flex: 1; min-width: 0;
}
.cat-tabs-scroll::-webkit-scrollbar { display: block; height: 3px; }
.cat-tabs-scroll::-webkit-scrollbar-track { background: #F3F4F6; border-radius: 9999px; }
.cat-tabs-scroll::-webkit-scrollbar-thumb { background: #D1D5DB; border-radius: 9999px; }
.cat-tab {
    display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px;
    border: 1.5px solid #E5E7EB; border-radius: 9999px; font-size: 13px;
    font-weight: 500; color: #374151; white-space: nowrap; cursor: pointer;
    background: white; text-decoration: none; transition: all 0.15s; flex-shrink: 0;
}
.cat-tab:hover { border-color: #C8102E; color: #C8102E; }
.cat-tab.active { background: #C8102E; border-color: #C8102E; color: white; }

/* Subcategory */
.subcat-row {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    padding: 14px 0 16px; border-bottom: 1px solid #F3F4F6;
    margin-bottom: 20px; animation: fadeDown 0.2s ease;
}
@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-5px); }
    to   { opacity: 1; transform: translateY(0); }
}
.subcat-all {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 14px; border: 1.5px solid #C8102E;
    border-radius: 9999px; font-size: 12px; font-weight: 600;
    color: #C8102E; background: white; text-decoration: none;
    white-space: nowrap; transition: all 0.15s; flex-shrink: 0;
}
.subcat-all:hover, .subcat-all.active { background: #C8102E; color: white; }
.subcat-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 14px; border: 1.5px solid #E5E7EB;
    border-radius: 9999px; font-size: 12px; font-weight: 500;
    color: #374151; background: white; text-decoration: none;
    white-space: nowrap; transition: all 0.15s; flex-shrink: 0;
}
.subcat-pill:hover { border-color: #C8102E; color: #C8102E; }
.subcat-pill.active { background: #FEE2E2; border-color: #C8102E; color: #C8102E; font-weight: 600; }

/* ===== MOBILE ===== */
@media(max-width:768px) {
  .browse-search-row {
    flex-wrap: wrap !important;
    gap: 8px !important;
    position: relative !important;
  }
  .browse-search-input {
    flex: 1 1 auto !important;
    min-width: 0 !important;
  }
  .browse-search-row button[type="submit"] {
    flex-shrink: 0 !important;
    width: auto !important;
  }
  .filter-dropdown-wrap {
    width: 100% !important;
    order: 3 !important;
    position: relative !important;
    z-index: 9999 !important;
  }
  .filter-dropdown-wrap .filter-btn {
    width: 100% !important;
    justify-content: center !important;
    box-sizing: border-box !important;
  }
  .filter-dropdown {
    right: auto !important;
    left: 0 !important;
    width: 100% !important;
    min-width: unset !important;
    top: calc(100% + 4px) !important;
    max-height: 260px !important;
    overflow-y: auto !important;
  }
}
</style>

<div class="container">
  <div class="browse-page">

    <div class="browse-top">
      <h1 class="browse-title"><?php echo esc_html($page_title); ?>
        <?php if($active_country && isset($country_names[$active_country])): ?>
        <span style="font-size:16px;font-weight:400;color:#6B7280"> — <?php echo $country_names[$active_country]['flag'].' '.esc_html($country_names[$active_country]['name']); ?></span>
        <?php endif; ?>
      </h1>
      <p class="listings-count"><?php echo esc_html($found); ?> listing<?php echo $found !== 1 ? 's' : ''; ?> found</p>
    </div>

    <!-- Search + Filter -->
    <form method="GET" class="browse-search-row">
      <?php if($active_country): ?>
      <input type="hidden" name="country" value="<?php echo esc_attr($active_country); ?>">
      <?php endif; ?>
      <?php if($active_sort && $active_sort !== 'newest'): ?>
      <input type="hidden" name="sort" value="<?php echo esc_attr($active_sort); ?>">
      <?php endif; ?>
      <input type="text" name="s" value="<?php echo esc_attr(get_search_query()); ?>"
        placeholder="Search listings by title..." class="browse-search-input">
      <button type="submit" class="filter-btn">Search</button>

      <!-- Sort Dropdown -->
      <div class="filter-dropdown-wrap">
        <button type="button" class="filter-btn <?php echo $active_sort !== 'newest' ? 'active' : ''; ?>" onclick="toggleSort(event)">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M3 6h18M6 12h12M9 18h6"/>
          </svg>
          <?php
          $sort_labels = ['newest'=>'Newest First','oldest'=>'Oldest First'];
          echo $sort_labels[$active_sort] ?? 'Sort';
          ?>
        </button>
        <div class="filter-dropdown" id="sort-dropdown">
          <div class="filter-dropdown-title">📊 Sort By</div>
          <?php
          $sort_base = $base_url ?? strtok($_SERVER['REQUEST_URI'], '?');
          $sort_params = $_GET; unset($sort_params['sort']);
          foreach(['newest'=>['label'=>'Newest First','icon'=>'🆕'],'oldest'=>['label'=>'Oldest First','icon'=>'🕰️']] as $val=>$opt):
            $sp = $sort_params; $sp['sort'] = $val;
            $sort_url = $sort_base . '?' . http_build_query($sp);
          ?>
          <a href="<?php echo esc_url($sort_url); ?>" class="sort-option <?php echo $active_sort===$val?'active':''; ?>">
            <span><?php echo $opt['icon']; ?></span>
            <span><?php echo $opt['label']; ?></span>
            <?php if($active_sort===$val): ?><span style="margin-left:auto">✓</span><?php endif; ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Country Filter Dropdown -->
      <div class="filter-dropdown-wrap">
        <button type="button" class="filter-btn <?php echo $active_country ? 'active' : ''; ?>" onclick="toggleFilter(event)">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/>
          </svg>
          <?php echo $active_country && isset($country_names[$active_country])
            ? $country_names[$active_country]['flag'].' '.esc_html($country_names[$active_country]['name'])
            : 'Country'; ?>
        </button>
        <div class="filter-dropdown" id="filter-dropdown">
          <div class="filter-dropdown-title">🌍 Filter by Country</div>
          <!-- Search inside dropdown -->
          <div style="padding:6px 8px">
            <input type="text" id="country-search" placeholder="Search country..."
              style="width:100%;padding:7px 10px;border:1.5px solid #E5E7EB;border-radius:6px;font-size:13px;outline:none;font-family:inherit"
              oninput="filterCountries(this.value)">
          </div>
          <div id="country-list">
          <?php
          $base_url_c  = strtok($_SERVER['REQUEST_URI'], '?');
          $base_params = $_GET;
          unset($base_params['country']);

          // Show countries that have listings first, then all others
          $listed = [];
          $all_c  = [];
          foreach($country_names as $code => $info) {
            if(in_array($code, $used_countries)) {
              $listed[$code] = $info;
            } else {
              $all_c[$code] = $info;
            }
          }
          $display_countries = $listed + $all_c;

          foreach($display_countries as $code => $info):
            $params     = $base_params;
            $params['country'] = $code;
            $filter_url = $base_url_c . '?' . http_build_query($params);
            $is_active_c = ($active_country === $code);
            $has_listings = in_array($code, $used_countries);
          ?>
          <a href="<?php echo esc_url($filter_url); ?>"
             class="filter-country-option <?php echo $is_active_c ? 'active' : ''; ?>"
             data-name="<?php echo esc_attr(strtolower($info['name'])); ?>">
            <span style="font-size:18px"><?php echo $info['flag']; ?></span>
            <span><?php echo esc_html($info['name']); ?></span>
            <?php if($has_listings): ?><span style="margin-left:auto;font-size:10px;color:#10B981;font-weight:700">●</span><?php endif; ?>
            <?php if($is_active_c): ?><span style="margin-left:4px">✓</span><?php endif; ?>
          </a>
          <?php endforeach; ?>
          </div>
          <?php if($active_country):
            $clear_url = $base_url_c;
            if(!empty($base_params)) $clear_url .= '?' . http_build_query($base_params);
          ?>
          <a href="<?php echo esc_url($clear_url); ?>" class="filter-clear">✕ Clear filter</a>
          <?php endif; ?>
        </div>
      </div>
    </form>

    <!-- Parent Category Tabs — hidden on mobile via JS -->
    <div class="cat-tabs-wrapper" style="display:none" id="cat-tabs-wrapper">
      <div class="cat-tabs-outer">
        <a href="<?php echo esc_url(get_post_type_archive_link('listing')); ?><?php echo $active_country ? '?country='.esc_attr($active_country) : ''; ?>"
           class="cat-tab cat-tab-fixed <?php echo !$is_cat ? 'active' : ''; ?>">
          All Categories
        </a>
        <div class="cat-tabs-scroll" id="cat-tabs-scroll">
          <?php if (!is_wp_error($all_cats) && !empty($all_cats)):
            foreach ($all_cats as $cat):
              $cat_link = get_term_link($cat);
              if (is_wp_error($cat_link)) continue;
              if($active_country) $cat_link .= '?country='.urlencode($active_country);
              $is_active = $is_cat && (
                $queried->term_id === $cat->term_id ||
                ($is_subcategory && $active_parent_id === $cat->term_id)
              );
          ?>
          <a href="<?php echo esc_url($cat_link); ?>"
             class="cat-tab <?php echo $is_active ? 'active' : ''; ?>">
            <?php echo dealboard_category_icon($cat->slug); ?> <?php echo esc_html($cat->name); ?>
          </a>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <!-- Subcategory Row -->
    <?php if ($is_cat && !empty($subcats) && !is_wp_error($subcats)): ?>
    <div class="subcat-row" style="display:none" id="subcat-row">
      <?php
      $parent_term = $is_subcategory ? get_term($active_parent_id,'listing_category') : $queried;
      $parent_link = !is_wp_error($parent_term) ? get_term_link($parent_term) : '#';
      if($active_country) $parent_link .= '?country='.urlencode($active_country);
      ?>
      <a href="<?php echo esc_url($parent_link); ?>"
         class="subcat-all <?php echo !$is_subcategory ? 'active' : ''; ?>">
        All <?php echo esc_html($parent_term->name); ?>
      </a>
      <?php foreach ($subcats as $sub):
        $sub_link = get_term_link($sub);
        if (is_wp_error($sub_link)) continue;
        if($active_country) $sub_link .= '?country='.urlencode($active_country);
        $sub_active = ($is_subcategory && $active_cat_id === $sub->term_id);
      ?>
      <a href="<?php echo esc_url($sub_link); ?>"
         class="subcat-pill <?php echo $sub_active ? 'active' : ''; ?>">
        <?php echo esc_html($sub->name); ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="margin-bottom:20px"></div>
    <?php endif; ?>

    <!-- Listings Grid -->
    <div class="listings-grid">
      <?php if (have_posts()):
        while (have_posts()): the_post();
          $price          = get_post_meta(get_the_ID(),'listing_price',true);
          $city           = get_post_meta(get_the_ID(),'listing_city',true);
          $currency       = get_post_meta(get_the_ID(),'listing_currency',true) ?: 'USD';
          $views          = get_post_meta(get_the_ID(),'listing_views',true) ?: 0;
          $listing_status = get_post_meta(get_the_ID(),'listing_status',true);
          $lc             = wp_get_post_terms(get_the_ID(),'listing_category');
          $cat_name       = (!empty($lc) && !is_wp_error($lc)) ? $lc[0]->name : '';
          $author         = get_the_author_meta('display_name');
          $time           = human_time_diff(get_the_time('U'), current_time('timestamp')).' ago';
      ?>
      <a href="<?php the_permalink(); ?>" class="listing-card">
        <div class="listing-image" style="position:relative">
          <?php if(has_post_thumbnail()): the_post_thumbnail('medium',['loading'=>'lazy']); else: ?>
          <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#F3F4F6;font-size:40px">📦</div>
          <?php endif; ?>

          <?php if($listing_status === 'expired'): ?>
          <div style="position:absolute;bottom:8px;left:8px;background:#DC2626;color:white;font-size:10px;font-weight:800;padding:4px 10px;border-radius:5px;z-index:2;letter-spacing:.8px;text-transform:uppercase;box-shadow:0 2px 6px rgba(0,0,0,.2)">
            ⛔ Expired
          </div>
          <?php endif; ?>

          <?php if($price): ?>
          <div class="listing-price" data-currency="<?php echo esc_attr($currency); ?>">
            <?php echo esc_html('$'.$price); ?>
          </div>
          <?php endif; ?>
          <?php if($cat_name): ?>
          <div class="listing-category-badge"><?php echo esc_html($cat_name); ?></div>
          <?php endif; ?>
          <div class="listing-seller"><?php echo esc_html(strtoupper(substr($author,0,2))); ?></div>
        </div>
        <div class="listing-body">
          <div class="listing-title"><?php the_title(); ?></div>
          <?php if($currency !== 'USD'): ?>
          <div class="listing-currency-note">Listed in <?php echo esc_html($currency); ?> · converted</div>
          <?php endif; ?>
          <div class="listing-meta">
            <?php if($city): ?><span>📍 <?php echo esc_html($city); ?></span><?php endif; ?>
            <span>👁 <?php echo esc_html($views); ?></span>
            <span>🕐 <?php echo esc_html($time); ?></span>
          </div>
        </div>
      </a>
      <?php endwhile;
      else: ?>
      <div class="empty-state" style="grid-column:1/-1">
        <div style="font-size:48px;margin-bottom:16px">🔍</div>
        <h3>No listings found</h3>
        <p>Try a different filter or <a href="<?php echo esc_url(get_post_type_archive_link('listing')); ?>" style="color:#C8102E">browse all listings</a></p>
      </div>
      <?php endif; ?>
    </div>

    <?php the_posts_pagination(['prev_text'=>'← Previous','next_text'=>'Next →','mid_size'=>2]); ?>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var isDesktop = window.innerWidth > 768;
  var catWrap   = document.getElementById('cat-tabs-wrapper');
  var subcatRow = document.getElementById('subcat-row');

  if(isDesktop) {
    if(catWrap)   catWrap.style.display   = 'block';
    if(subcatRow) subcatRow.style.display = 'flex';
  } else {
    if(subcatRow) subcatRow.style.display = 'flex';
  }

  var scroll = document.getElementById('cat-tabs-scroll');
  if(scroll) {
    var active = scroll.querySelector('.cat-tab.active');
    if(active) active.scrollIntoView({behavior:'smooth',block:'nearest',inline:'center'});
  }
});

function toggleFilter(e) {
  e.stopPropagation();
  document.getElementById('sort-dropdown').classList.remove('open');
  var dd = document.getElementById('filter-dropdown');
  dd.classList.toggle('open');
}

function toggleSort(e) {
  e.stopPropagation();
  document.getElementById('filter-dropdown').classList.remove('open');
  var dd = document.getElementById('sort-dropdown');
  dd.classList.toggle('open');
}

document.addEventListener('click', function() {
  var fd = document.getElementById('filter-dropdown');
  var sd = document.getElementById('sort-dropdown');
  if(fd) fd.classList.remove('open');
  if(sd) sd.classList.remove('open');
});

function filterCountries(val) {
  var items = document.querySelectorAll('#country-list .filter-country-option');
  val = val.toLowerCase();
  items.forEach(function(item) {
    var name = item.getAttribute('data-name') || '';
    item.style.display = name.indexOf(val) !== -1 ? 'flex' : 'none';
  });
}
</script>

<?php get_footer(); ?>
