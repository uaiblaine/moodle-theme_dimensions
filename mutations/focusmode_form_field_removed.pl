# Keep the focus mode box in the course form while the master switch is off: a question with no effect.
s/\n[ \t]*self::remove_focusmode_field\(\$hook->mform\);(?=\n)//;
