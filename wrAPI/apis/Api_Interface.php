<?php

interface Api_Interface { // Named with an Underscore to disallow it from being called by wrAPI's construct.

	/*
	 * Public Methods
	 */
	public function connect( $arguments );
	public function __call( $name, $arguments );
}
