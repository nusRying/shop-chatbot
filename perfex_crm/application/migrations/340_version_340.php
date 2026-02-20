<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_340 extends CI_Migration
{
    public function up(): void
    {
        $this->db->query('ALTER TABLE `' . db_prefix() . "itemable` ADD `is_optional` TINYINT NOT NULL DEFAULT '0' AFTER `unit`, ADD `is_selected` TINYINT NOT NULL DEFAULT '1' AFTER `is_optional`;");

        $this->db->query('ALTER TABLE `' . db_prefix() . 'project_notes` ADD `title` VARCHAR(255) NULL AFTER `project_id`;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'project_notes` ADD COLUMN `dateadded` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `staff_id`');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'templates` ADD `content_type` VARCHAR(20) NOT NULL DEFAULT "html" AFTER `content`;');
    }
}
