-- Reset Password Script for i-Klinik
-- Run this if you've already imported the database and login is not working
-- This sets all user passwords to '123456' (plain text)

USE `eklinik`;

-- Update all user passwords to plain text '123456'
UPDATE `users` SET `password` = '123456';

-- Verify the update
SELECT `name`, `email`, `matrix_no`, `role`, `password` FROM `users`;
