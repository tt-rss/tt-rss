<?php
class Handler_Administrative extends Handler_Protected {
   public function before(string $method): bool {
      if (parent::before($method)) {
         if (($_SESSION["access_level"] ?? 0) >= UserHelper::ACCESS_LEVEL_ADMIN) {
            return true;
         }
      }
      return false;
   }
}
