# Under "without password" a guest instance with a password says nothing.
s/            if \(\$passwordset && \$setting != THEME_BOOST_UNION_SETTING_GUESTACCESSHINT_ALWAYS\) \{/            if (false) {/;
