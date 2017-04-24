<?php
/* @var $this Mouf\Database\TDBM\Controllers\TdbmInstallController */ ?>
<h1>Setting up TDBM</h1>

<p>By clicking the link below, you will automatically generate DAOs and Beans for TDBM. These beans and DAOs will be written in the directories you specify in the form below.</p>

<form action="generate" method="post" class="form-horizontal">
<input type="hidden" id="selfedit" name="selfedit" value="<?php echo plainstring_to_htmlprotected($this->selfedit) ?>" />

<?php if (!$this->autoloadDetected) {
    ?>
<div class="alert">Warning! TDBM could not detect the autoload section of your composer.json file.
Unless you are developing your own autoload system, you should configure <strong>composer.json</strong> to <a href="http://getcomposer.org/doc/01-basic-usage.md#autoloading" target="_blank">define a source directory and a root namespace using PSR-0</a>.</div>
<?php

} ?>

<div class="control-group">
	<label class="control-label">Dao namespace:</label>
	<div class="controls">
		<input type="text" name="daonamespace" value="<?php echo plainstring_to_htmlprotected($this->daoNamespace) ?>"></input>
		<span class="help-block">The namespace for the DAOs. Be sure to type a namespace that is registered in the "autoload" section of your <em>composer.json</em> file. Otherwise, the composer autoloader will fail to load your classes.</span>
	</div>
</div>
<div class="control-group">
	<label class="control-label">Bean namespace:</label>
	<div class="controls">
		<input type="text" name="beannamespace" value="<?php echo plainstring_to_htmlprotected($this->beanNamespace) ?>"></input>
		<span class="help-block">The namespace for the beans. Be sure to type a namespace that is registered in the "autoload" section of your <em>composer.json</em> file. Otherwise, the composer autoloader will fail to load your classes.</span>
	</div>
</div>

<div class="control-group">
	<label class="control-label">Use custom <code>composer.json</code>:</label>
	<div class="controls">
		<input type="checkbox" id="useCustomComposer" name="useCustomComposer" value="1" <?php echo $this->useCustomComposer ? 'checked="checked"' : '' ?>></input>
		<span class="help-block">Click if your Composer file is not named <code>composer.json</code> or if you want to write Daos and Beans in another Composer package.</span>
	</div>
</div>

<div class="control-group" id="composerFileGroup">
	<label class="control-label">Path to Composer file (<code>composer.json</code>):</label>
	<div class="controls">
		<input type="text" name="composerFile" value="<?php echo plainstring_to_htmlprotected($this->composerFile) ?>"></input>
		<span class="help-block">
			The path to the Composer file of the package that will contain generated DAOs and Beans.<br />
			The path is relative to the project root path.<br />
			For example: `vendor/company/package/composer.json`
		</span>
	</div>
</div>

<div class="control-group">
	<div class="controls">
		<button name="action" value="generate" type="submit" class="btn btn-danger">Install TDBM</button>
	</div>
</div>
</form>
<script>
	function changeDefaultPath() {
		if($('#useCustomComposer').prop('checked')) {
			$('#composerFileGroup').show();
		}
		else {
			$('#composerFileGroup').hide();
		}
	}
	$(document).ready(function () {
		$('#useCustomComposer').on('change', changeDefaultPath);
		changeDefaultPath();
	})
</script>
