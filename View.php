<!--
	This is a view within the custom framework. <render> tags act similarly to `extends`
	in Twig and Blade.
-->

<render>layout.php</render>

<filler for="content">
	<?php \APP\Util\RedactedUtility::section_title('Log In') ?>

	<form method="post" novalidate>
		<div class="container with-cards">

			<div class="row">
				<div class="col-sm-6">
					<div class="card">
						<div class="row card-header"><div class='col-xs-12'><h2>Need an Account?</h2></div></div>

						<div class="row card-row">
							<div class="col-xs-12">
								<p class='lead'>It's free to get started. Begin crafting sites for your properties within minutes!</p>
							</div>
						</div>

						<div class="row card-footer card-actions">
							<div class="col-xs-12 form-group">
								<a href='<?= \APP\Util\RedactedUtility::root_url('signup') ?>' class="btn btn-block purewhite"><i class="fa fa-id-card"></i> Sign Up</a>
							</div>
						</div>
					</div>
				</div>
				
				<div class="col-sm-6">
					<div class="card<?= ($view->getError('errors') ? ' card-with-stripe' : '') ?>">
						<?php if ($view->getError('errors')): ?>
							<div class="card-stripe danger"></div>
						<?php endif ?>

						<div class="row card-header"><div class='col-xs-12'><h2>Welcome Back!</h2></div></div>

						<?php if ($view->getError('errors')) {
							print \APP\Util\RedactedUtility::alert('danger', 'Uh oh!', $view->getError('errors'));
						} ?>

						<div class="row card-row">
							<div class="col-xs-12">
								<div class="form-group">
									<label>Email Address</label>
									<input type="email" class="form-control" name="email">
								</div>

								<div class="form-group">
									<label>Password</label>
									<input type="password" class="form-control" name="password">
								</div>

								<p class='text-right'>Did you <a href='<?= \APP\Util\RedactedUtility::root_url('recover') ?>'>forget your password</a>?</p>
							</div>
						</div>

						<div class="row card-footer card-actions">
							<div class="col-xs-12 form-group">
								<button type="submit" class="btn btn-block success"><i class="fa fa-check-circle"></i> Log In</button>
							</div>
						</div>
					</div>
				</div>
			</div>

		</div>
	</form>
</filler>
