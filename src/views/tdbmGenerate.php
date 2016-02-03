<?php /* @var $this TdbmController */ ?>
<h1>Generate DAOs</h1>

<p>By clicking the link below, you will automatically generate DAOs and Beans for TDBM. These beans and DAOs will be written in the /dao and /dao/beans namespace.</p>

<form action="generate" method="post" class="form-horizontal">
<input type="hidden" id="name" name="name" value="<?php echo plainstring_to_htmlprotected($this->instanceName) ?>" />
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
	<label class="control-label">DaoFactory class name:</label>
	<div class="controls">
		<input type="text" name="daofactoryclassname" value="<?php echo plainstring_to_htmlprotected($this->daoFactoryName) ?>"></input>
		<span class="help-block">Do not specify a namespace, just the class name here. The namespace for the DaoFactory will be the DAOs namespace.</span>
	</div>
</div>

<div class="control-group">
	<label class="control-label">DaoFactory instance name:</label>
	<div class="controls">
		<input type="text" name="daofactoryinstancename" value="<?php echo plainstring_to_htmlprotected($this->daoFactoryInstanceName) ?>"></input>
	</div>
</div>

<div class="control-group">
	<label class="control-label">Store dates / timestamps in UTC:</label>
	<div class="controls">
		<input type="checkbox" name="storeInUtc" value="1" <?php echo $this->storeInUtc ? 'checked="checked"' : '' ?>></input>
		<span class="help-block">Select this option if you want timestamps to be stored in UTC.
		If your application supports several time zones, you should select this option to store all dates in
		the same time zone.</span>
	</div>
</div>

<div class="control-group">
	<label class="control-label">Use the default path (compute with PSR):</label>
	<div class="controls">
		<input type="checkbox" id="defaultPath" name="defaultPath" value="1" <?php echo $this->defaultPath ? 'checked="checked"' : '' ?>></input>
		<span class="help-block">Select this option if you want to use the default folder to store daos and beans.</span>
	</div>
</div>

<div class="control-group" id="storePathGroup">
	<label class="control-label">Location to store daos and beans:</label>
	<div class="controls">
		<input type="text" name="storePath" value="<?php echo plainstring_to_htmlprotected($this->storePath) ?>"></input>
		<span class="help-block">
			This field is to change the folder of daos and beans. This automatically start with root path.<br />
			Don't add the psr folder at the end. End with separator /.<br />
			For exemple: vendor/company/package/src/<br />
			<b>Caution:</b> The folder filled must be contain a composer.json. The Psr in the composer must be 
		</span>
	</div>
</div>

<div class="control-group">
	<div class="controls">
		<button name="action" value="generate" type="submit" class="btn btn-danger">Generate DAOs</button>
	</div>
</div>
</form>
<script>
	function changeDefaultPath() {
		if($('#defaultPath').prop('checked')) {
			$('#storePathGroup').hide();
		}
		else {
			$('#storePathGroup').show();
		}
	}
	$(document).ready(function () {
		$('#defaultPath').on('change', changeDefaultPath);
		changeDefaultPath();
	})
</script>