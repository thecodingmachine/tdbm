<?php /* @var $this TdbmInstallController */ ?>
<h1>Setting up TDBM</h1>

<p>By clicking the link below, you will automatically generate DAOs and Beans for TDBM. These beans and DAOs will be written in the directories you specify in the form below.</p>

<form action="generate" method="post">
<input type="hidden" id="selfedit" name="selfedit" value="<?php echo plainstring_to_htmlprotected($this->selfedit) ?>" />

<div>
<label>Dao directory:</label><input type="text" name="daodirectory" value="<?php echo plainstring_to_htmlprotected($this->daoDirectory) ?>"></input>
</div>
<div>
<label>Bean directory:</label><input type="text" name="beandirectory" value="<?php echo plainstring_to_htmlprotected($this->beanDirectory) ?>"></input>
</div>
<div>
<label>Keep support for previous DAOs:</label><input type="checkbox" name="keepSupport" value="1"></input>
</div>

<div>
	<button name="action" value="generate" type="submit">Install TDBM</button>
</div>
</form>