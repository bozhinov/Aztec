<?php

namespace Aztec\Encoder;

class BinaryDataEncoder
{
   # CODE_UPPER_BS = 31;

    public function encode($data)
    {
		$bstream = [];
		$data = array_values(unpack('C*', $data));
		$len = count($data);

		# Used to split the string in (2048 + 32 - 1) long pieces
		# Barcode can't store that much anyway
		if ($len >= 32) {
			$bstream[] = [31, 5];
			$bstream[] = [0, 5];
			# Used to be $len - 32 but that resulted 
			# in AK at the end of the decoded string
			$bstream[] = [($len - 31), 11];
        } else {
			$bstream[] = [31, 5];
			$bstream[] = [$len, 5];
        }

		foreach($data as $ord){
			$bstream[] = [$ord, 8];
		}

        return $bstream;
    }
}
