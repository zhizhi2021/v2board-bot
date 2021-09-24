ALTER TABLE `v2_user`
ADD `last_checkin_at` int(11) NOT NULL DEFAULT '0' AFTER `transfer_enable`;