<?php

namespace Aztec\Encoder;

use Aztec\BitArray;

class BinaryDataEncoder
{
   # CODE_UPPER_BS = 31;

    public function encode($data)
    {
        $result = new BitArray();

		$data = array_values(unpack('C*', $data));
		$len = count($data);

		# Used to split the string in (2048 + 32 - 1) long pieces
		# Barcode can't store that much anyway
		if ($len >= 32) {
            $result->append(31, 5);
            $result->append(0, 5);
			# Used to be $len - 32 but that resulted 
			# in AK at the end of the decoded string
            $result->append(($len - 31), 11);
        } else {
            $result->append(31, 5);
            $result->append($len, 5);
        }

		foreach($data as $ord){
			$result->append($ord, 8);
		}

        return $result;
    }
}
