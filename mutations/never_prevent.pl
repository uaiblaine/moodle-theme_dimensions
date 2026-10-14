# Never prevent anything. The whole class becomes a no-op.
s/return core_user::VIEWPROFILE_PREVENT;/return core_user::VIEWPROFILE_DO_NOT_PREVENT;/;
