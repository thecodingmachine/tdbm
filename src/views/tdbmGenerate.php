<?php /* @var $this TdbmController */ ?>
<h1>Generate DAOs</h1>

<p>By clicking the link below, you will automatically generate DAOs and Beans for TDBM. These beans and DAOs will be written in the /dao and /dao/beans directory.</p>

<form action="generate" method="post">
<input type="hidden" id="name" name="name" value="<?php echo plainstring_to_htmlprotected($this->instanceName) ?>" />
<input type="hidden" id="selfedit" name="selfedit" value="<?php echo plainstring_to_htmlprotected($this->selfedit) ?>" />

<div>
<label>Dao directory:</label><input type="text" name="daodirectory" value="<?php echo plainstring_to_htmlprotected($this->daoDirectory) ?>"></input>
</div>
<div>
<label>Bean directory:</label><input type="text" name="beandirectory" value="<?php echo plainstring_to_htmlprotected($this->beanDirectory) ?>"></input>
</div>

<div>
<label>DaoFactory class name:</label><input type="text" name="daofactoryclassname" value="<?php echo plainstring_to_htmlprotected($this->daoFactoryName) ?>"></input>
</div>

<div>
<label>DaoFactory instance name:</label><input type="text" name="daofactoryinstancename" value="<?php echo plainstring_to_htmlprotected($this->daoFactoryInstanceName) ?>"></input>
</div>

<div>
<label>Keep support for previous DAOs:</label><input type="checkbox" name="keepSupport"></input>
</div>

<div>
	<button name="action" value="generate" type="submit">Generate DAOs</button>
</div>
</form>