<?php
interface IAuthModule2 extends IAuthModule {
	public function change_password(int $owner_uid, string $old_password, string $new_password) : string;
}
