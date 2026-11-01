# Cap the candidates before the gate and the batch: withheld courses push joinable ones out again.
s/array_chunk\(\$rows, self::OPEN_CAP, true\)/array_chunk(array_slice(\$rows, 0, self::OPEN_CAP, true), self::OPEN_CAP, true)/;
