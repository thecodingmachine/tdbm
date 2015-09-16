<?php /* @var $this TdbmController */ ?>
<h1>Generate DAOs</h1>

<p>By clicking the link below, you will automatically generate DAOs and Beans for TDBM. These beans and DAOs will be written in the /dao and /dao/beans namespace.</p>

<form action="generate" method="post" class="form-horizontal">
<input type="hidden" id="name" name="name" value="<?php echo plainstring_to_htmlprotected($this->instanceName) ?>" />
<input type="hidden" id="selfedit" name="selfedit" value="<?php echo plainstring_to_htmlprotected($this->selfedit) ?>" />

<?php if (!$this->autoloadDetected) { ?>
<div class="alert">Warning! TDBM could not detect the autoload section of your composer.json file.
Unless you are developing your own autoload system, you should configure <strong>composer.json</strong> to <a href="http://getcomposer.org/doc/01-basic-usage.md#autoloading" target="_blank">define a source directory and a root namespace using PSR-0</a>.</div>
<?php } ?>

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
	<label class="control-label">Keep support for previous DAOs:</label>
	<div class="controls">
		<input type="checkbox" name="keepSupport" <?php echo $this->keepSupport?'checked="checked"':"" ?>></input>
		<span class="help-block">DAOs generated before TDBM 2.3 had a different method signature. This will ensure this signature
		is respected. Use this only if you are migrating legacy code.</span>
	</div>
</div>
<div class="control-group">
	<label class="control-label">Cast dates as <code>DateTimeImmutable</code>:</label>
	<div class="controls">
		<input type="checkbox" name="castDatesToDateTime" value="1" <?php echo $this->castDatesToDateTime?'checked="checked"':"" ?>></input>
<span class="help-block">Select this option if you want dates to be returned as <code>DateTimeImmutable</code>.
This is highly recommended. If you do not select this box, getters and setters will return / expect a timestamp
(this was the default behaviour up to TDBM 3.3).</span>
	</div>
</div>
<div class="control-group">
	<label class="control-label">Store dates / timestamps in UTC:</label>
	<div class="controls">
		<input type="checkbox" name="storeInUtc" value="1" <?php echo $this->storeInUtc?'checked="checked"':"" ?>></input>
		<span class="help-block">Select this option if you want timestamps to be stored in UTC.
		If your application supports several time zones, you should select this option to store all dates in
		the same time zone.</span>
	</div>
</div>

<div class="control-group">
	<div class="controls">
		<button name="action" value="generate" type="submit" class="btn btn-danger">Generate DAOs</button>
	</div>
</div>
</form>