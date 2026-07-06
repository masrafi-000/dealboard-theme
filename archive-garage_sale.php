<?php get_header(); ?>

<?php
$found = $wp_query->found_posts ?? 0;
?>

<style>
.gs-archive { padding: 32px 0 60px; background: #F9FAFB; min-height: 60vh; }

/* Hero banner */
.gs-hero {
  background: linear-gradient(135deg, #0A1628 0%, #1A2B4A 100%);
  padding: 40px 0;
  margin-bottom: 32px;
}
.gs-hero-inner {
  max-width: 1200px; margin: 0 auto; padding: 0 24px;
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 20px;
}
.gs-hero-left h1 {
  font-size: 28px; font-weight: 800; color: white; margin-bottom: 6px;
  display: flex; align-items: center; gap: 10px;
}
.gs-hero-left p { font-size: 14px; color: rgba(255,255,255,.65); }
.gs-hero-right { display: flex; gap: 10px; flex-wrap: wrap; }

.btn-list-sale {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 11px 22px; background: #C8102E; color: white;
  font-size: 14px; font-weight: 700; border-radius: 9999px;
  text-decoration: none; transition: background .15s;
}
.btn-list-sale:hover { background: #A50E26; color: white; }

.btn-view-toggle {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 9px 16px; background: rgba(255,255,255,.1);
  color: white; font-size: 13px; font-weight: 500;
  border-radius: 8px; border: 1px solid rgba(255,255,255,.2);
  text-decoration: none; transition: all .15s; cursor: pointer;
}
.btn-view-toggle:hover, .btn-view-toggle.active {
  background: rgba(255,255,255,.2); color: white;
}

/* Search bar */
.gs-search-wrap {
  max-width: 1200px; margin: 0 auto 28px; padding: 0 24px;
  display: flex; gap: 10px;
}
.gs-search-input {
  flex: 1; padding: 11px 18px 11px 44px;
  border: 1.5px solid #E5E7EB; border-radius: 10px;
  font-size: 14px; outline: none; background: white;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%239CA3AF' stroke-width='2' viewBox='0 0 24 24'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: 14px center;
  font-family: inherit; transition: border-color .15s;
}
.gs-search-input:focus { border-color: #C8102E; }
.gs-search-btn {
  padding: 11px 24px; background: #C8102E; color: white;
  font-size: 14px; font-weight: 700; border: none; border-radius: 10px;
  cursor: pointer; font-family: inherit; transition: background .15s;
}
.gs-search-btn:hover { background: #A50E26; }

/* Stats bar */
.gs-stats {
  max-width: 1200px; margin: 0 auto 20px; padding: 0 24px;
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 12px;
}
.gs-stats-left {
  font-size: 14px; color: #6B7280;
}
.gs-stats-left strong { color: #111; }

/* Grid */
.gs-grid {
  max-width: 1200px; margin: 0 auto; padding: 0 24px;
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;
}
@media(max-width:900px) { .gs-grid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:560px) { .gs-grid { grid-template-columns: 1fr; } }

/* Card */
.gs-card {
  background: white; border-radius: 14px;
  border: 1px solid #E5E7EB; overflow: hidden;
  transition: all .2s ease; text-decoration: none; color: inherit;
  display: flex; flex-direction: column;
}
.gs-card:hover {
  box-shadow: 0 12px 28px rgba(0,0,0,.1);
  transform: translateY(-4px);
  border-color: transparent;
}

.gs-card-img {
  position: relative; height: 200px;
  overflow: hidden; background: #FEF3C7;
  flex-shrink: 0;
}
.gs-card-img img {
  width: 100%; height: 100%; object-fit: cover;
  transition: transform .3s ease;
}
.gs-card:hover .gs-card-img img { transform: scale(1.05); }

.gs-card-placeholder {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  font-size: 56px; background: linear-gradient(135deg,#FEF3C7,#FDE68A);
}

.gs-date-badge {
  position: absolute; top: 12px; left: 12px;
  background: rgba(10,22,40,.85); color: white;
  font-size: 11px; font-weight: 700; padding: 5px 12px;
  border-radius: 9999px; backdrop-filter: blur(4px);
}

.gs-status-badge {
  position: absolute; top: 12px; right: 12px;
  font-size: 11px; font-weight: 700; padding: 4px 10px;
  border-radius: 9999px;
}
.gs-status-upcoming { background: #D1FAE5; color: #065F46; }
.gs-status-today { background: #C8102E; color: white; }
.gs-status-past { background: #F3F4F6; color: #6B7280; }

.gs-card-body { padding: 16px; flex: 1; display: flex; flex-direction: column; }

.gs-card-title {
  font-size: 15px; font-weight: 700; color: #111;
  margin-bottom: 4px; line-height: 1.3;
}
.gs-card-subtitle {
  font-size: 13px; color: #6B7280; margin-bottom: 12px;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.gs-card-meta {
  display: flex; flex-direction: column; gap: 5px;
  font-size: 12px; color: #6B7280; margin-top: auto;
}
.gs-card-meta span {
  display: flex; align-items: center; gap: 6px;
}

/* Empty state */
.gs-empty {
  grid-column: 1/-1; text-align: center; padding: 60px 20px;
}
.gs-empty-icon { font-size: 56px; margin-bottom: 16px; }
.gs-empty h3 { font-size: 20px; font-weight: 700; color: #111; margin-bottom: 8px; }
.gs-empty p { font-size: 14px; color: #6B7280; margin-bottom: 24px; }
</style>

<!-- Hero -->
<div class="gs-hero">
  <div class="gs-hero-inner">
    <div class="gs-hero-left">
      <h1>
        <span style="font-size:28px">📍</span>
        Garage Sales
      </h1>
      <p>Find sales happening in your area this weekend</p>
    </div>
    <div class="gs-hero-right">
      <a href="<?php echo esc_url(home_url('/list-garage-sale')); ?>" class="btn-list-sale">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        List Your Sale
      </a>
      <a href="#" class="btn-view-toggle active" id="view-grid" onclick="setView('grid',this)">
        <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M3 3h7v7H3zm11 0h7v7h-7zM3 14h7v7H3zm11 0h7v7h-7z"/></svg>
        Grid
      </a>
      <a href="#" class="btn-view-toggle" id="view-list" onclick="setView('list',this)">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        List
      </a>
    </div>
  </div>
</div>

<!-- Search -->
<form method="GET" class="gs-search-wrap">
  <input type="text" name="s" value="<?php echo esc_attr(get_search_query()); ?>"
    placeholder="Search by title, city, or items..." class="gs-search-input">
  <button type="submit" class="gs-search-btn">Search</button>
</form>

<!-- Stats -->
<div class="gs-stats">
  <div class="gs-stats-left">
    <strong><?php echo esc_html($found); ?></strong> sale<?php echo $found !== 1 ? 's' : ''; ?> found
    <?php if(get_search_query()): ?> for "<strong><?php echo esc_html(get_search_query()); ?></strong>"<?php endif; ?>
  </div>
</div>

<!-- Grid -->
<div class="gs-grid" id="gs-grid">
  <?php if(have_posts()):
    while(have_posts()): the_post();
      $date  = get_post_meta(get_the_ID(),'sale_date_start',true);
      $start = get_post_meta(get_the_ID(),'sale_time_start',true);
      $end   = get_post_meta(get_the_ID(),'sale_time_end',true);
      $addr  = get_post_meta(get_the_ID(),'sale_address',true);
      $city  = get_post_meta(get_the_ID(),'sale_city',true);
      $items = get_post_meta(get_the_ID(),'sale_items',true);
      $date_fmt = $date ? date('D, M j, Y', strtotime($date)) : '';
      $time_fmt = ($start && $end) ? date('g:i A',strtotime($start)).' – '.date('g:i A',strtotime($end)) : '';
      $location = implode(', ', array_filter([$city]));

      // Status
      $status_label = 'Upcoming';
      $status_class = 'gs-status-upcoming';
      if($date) {
        $today = date('Y-m-d');
        if($date < $today) { $status_label = 'Ended'; $status_class = 'gs-status-past'; }
        elseif($date === $today) { $status_label = 'Today!'; $status_class = 'gs-status-today'; }
      }
  ?>
  <a href="<?php the_permalink(); ?>" class="gs-card">
    <div class="gs-card-img">
      <?php if(has_post_thumbnail()): ?>
        <?php the_post_thumbnail('medium', ['loading'=>'lazy','style'=>'width:100%;height:100%;object-fit:cover']); ?>
      <?php else: ?>
        <div class="gs-card-placeholder">🏡</div>
      <?php endif; ?>
      <?php if($date_fmt): ?>
      <div class="gs-date-badge"><?php echo esc_html($date_fmt); ?></div>
      <?php endif; ?>
      <div class="gs-status-badge <?php echo $status_class; ?>"><?php echo $status_label; ?></div>
    </div>
    <div class="gs-card-body">
      <div class="gs-card-title"><?php the_title(); ?></div>
      <?php if($items): ?>
      <div class="gs-card-subtitle"><?php echo esc_html($items); ?></div>
      <?php endif; ?>
      <div class="gs-card-meta">
        <?php if($location): ?>
        <span>
          <svg width="12" height="12" fill="none" stroke="#C8102E" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
          <?php echo esc_html($location); ?>
        </span>
        <?php endif; ?>
        <?php if($date_fmt): ?>
        <span>
          <svg width="12" height="12" fill="none" stroke="#C9A84C" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <?php echo esc_html($date_fmt); ?>
        </span>
        <?php endif; ?>
        <?php if($time_fmt): ?>
        <span>
          <svg width="12" height="12" fill="none" stroke="#6B7280" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <?php echo esc_html($time_fmt); ?>
        </span>
        <?php endif; ?>
      </div>
    </div>
  </a>
  <?php endwhile;
  else: ?>
  <div class="gs-empty">
    <div class="gs-empty-icon">🏡</div>
    <h3>No garage sales found</h3>
    <p>Be the first to list your garage sale in your area!</p>
    <a href="<?php echo esc_url(home_url('/list-garage-sale')); ?>" class="btn-list-sale">
      + List Your Garage Sale
    </a>
  </div>
  <?php endif; ?>
</div>

<!-- Pagination -->
<div style="max-width:1200px;margin:0 auto;padding:0 24px">
  <?php the_posts_pagination(['prev_text'=>'← Previous','next_text'=>'Next →','mid_size'=>2]); ?>
</div>

<script>
function setView(view, btn) {
  event.preventDefault();
  document.querySelectorAll('.btn-view-toggle').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  var grid = document.getElementById('gs-grid');
  if(view === 'list') {
    grid.style.gridTemplateColumns = '1fr';
  } else {
    grid.style.gridTemplateColumns = '';
  }
}
</script>

<?php get_footer(); ?>
