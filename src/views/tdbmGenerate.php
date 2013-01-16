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
	<label class="control-label">Source directory:</label>
	<div class="controls">
		<input type="text" name="sourcedirectory" value="<?php echo plainstring_to_htmlprotected($this->sourceDirectory) ?>"></input>
		<span class="help-block">This is the directory containing your source code (it should be configured in your the "autoload" section of your <em>composer.json</em> file.</span>
	</div>
</div>
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
		<span class="help-block">Do not specify a package, just the class name here. The package for the DaoFactory will be the DAOs namespace.</span>
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
		<input type="checkbox" name="keepSupport"></input>
		<span class="help-block">DAOs generated before TDBM 2.3 had a different method signature. This will ensure this signature
		is respected. Use this only if you are migrating legacy code.</span>
	</div>
</div>

<div class="control-group">
	<div class="controls">
		<button name="action" value="generate" type="submit" class="btn btn-danger">Generate DAOs</button>
	</div>
</div>
</form>