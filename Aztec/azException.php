<?php

namespace Aztec;

class azException extends \Exception
{
	public static function InvalidInput($text)
	{
		return new static(sprintf('Aztec: %s', $text));
	}

	public static function EncoderError($text)
	{
		return new static(sprintf('Aztec: %s', $text));
	}
}
