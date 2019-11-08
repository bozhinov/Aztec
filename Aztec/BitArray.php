<?php

/*
 * Copyright 2013 Metzli and ZXing authors
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

namespace Aztec;

class BitArray
{
    private $data;

    public function __construct($length = 0)
    {
        $this->data = [];
		if ($length != 0){
			 $this->data = array_pad($this->data, $length, 0);
		}
    }

    public function getLength()
    {
        return count($this->data);
    }

    public function get($index)
    {
        return $this->data[$index];
    }

    public function append($data, $bits = 1)
    {
        for ($i = $bits - 1; $i >= 0; $i--) {
            $this->data[] = ($data >> $i) & 1;
        }
    }
}
