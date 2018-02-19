<?php get_header(); ?>

	<main class="cd-main-content">
		
		<section class="categories">

			<div class="container-fluid">
				<div class="row">
					<div class="col-sm-12 video-header-archive">
						<h3 class="pull-left"><?php printf( __( 'Search Results for: %s', 'streamium' ), get_search_query() ); ?></h3>
						<?php if(get_theme_mod( 'streamium_enable_premium' )) : ?>
							<div class="streamium-drop-dropdown-wrapper open-to-left">
								<a class="streamium-drop-dropdown-trigger" href="#0"><?php _e( 'FILTER', 'streamium' ); ?></a>
								<nav class="streamium-drop-dropdown">
									<ul class="streamium-drop-dropdown-content">
										<li><a class="search-search-filter" data-type="day" href="?s=all&date=day"><?php _e( '1 Day Ago', 'streamium' ); ?></a></li>
									  	<li><a class="search-search-filter" data-type="week" href="?s=all&date=week"><?php _e( '1 Week Ago', 'streamium' ); ?></a></li>
									  	<?php
									  		$begin = new DateTime( '2015-01-01' );
											$end = new DateTime();
											$interval = DateInterval::createFromDateString('1 year');
											$period = new DatePeriod($begin, $interval, $end);
											foreach ( array_reverse(iterator_to_array($period)) as $dt ) : ?>
												<li><a class="search-search-filter" data-type="<?php echo $dt->format( "Y" ); ?>" href="?s=all&date=<?php echo $dt->format( "Y" ); ?>"><?php echo $dt->format( "Y" ); ?></a></li>
										<?php 
											endforeach; 
										?>
									  	<?php
									  		$begin = new DateTime( '2015-01-01' );
											$end = new DateTime();
											$interval = DateInterval::createFromDateString('1 month');
											$period = new DatePeriod($begin, $interval, $end);
											foreach ( array_reverse(iterator_to_array($period)) as $dt ) : ?>
												<li><a class="search-search-filter" data-type="<?php echo $dt->format( "Y/m/d" ); ?>" href="?s=all&date=<?php echo $dt->format( "Y/m/d" ); ?>"><?php echo $dt->format( "M Y" ); ?></a></li>
										<?php 
											endforeach; 
										?>
									</ul> <!-- .cd-dropdown-content -->
								</nav> <!-- .cd-dropdown -->
							</div> <!-- .cd-dropdown-wrapper -->
						<?php endif; ?>
					</div><!--/.col-sm-12-->
				</div><!--/.row-->
			</div><!--/.container-->

			<div id="search-watched"></div>

		</section><!--/.videos-->

	</main><!--/.main content-->
		
<?php get_footer(); ?>