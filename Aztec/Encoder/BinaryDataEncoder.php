<?php

/*
 * Copyright 2013 Metzli authors
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Aztec\Encoder;

use Aztec\BitArray;

class BinaryDataEncoder
{
   # CODE_UPPER_BS = 31;

    public function encode($data)
    {
        $result = new BitArray();

        while (strlen($data) >= 32) {
            $chunkLength = min(strlen($data), (2048 + 32 - 1));
            $result->append(31, 5);
            $result->append(0, 5);
            $result->append(($chunkLength - 32), 11);
			$bytes = substr($data, 0, $chunkLength);
			for ($i = 0; $i < $chunkLength; $i++) {
				$result->append(ord($bytes[$i]), 8);
			}
            $data = substr($data, $chunkLength);
        }
		
		$len = strlen($data);
        if ($len > 0) {
            $result->append(31, 5);
            $result->append($len, 5);
			for ($i = 0; $i < $len; $i++) {
				$result->append(ord($data[$i]), 8);
			}
        }

        return $result;
    }
}
