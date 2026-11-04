# Drop core's footer under the upcoming block whoever answers it.
s/        return \$applies && \$block === self::BLOCK_UPCOMING;/        return \$block === self::BLOCK_UPCOMING;/;
