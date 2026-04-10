/*
 Navicat Premium Dump SQL

 Source Server         : 1
 Source Server Type    : MySQL
 Source Server Version : 80045 (8.0.45)
 Source Host           : localhost:3306
 Source Schema         : work1

 Target Server Type    : MySQL
 Target Server Version : 80045 (8.0.45)
 File Encoding         : 65001

 Date: 09/04/2026 22:47:10
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for admin
-- ----------------------------
DROP TABLE IF EXISTS `admin`;
CREATE TABLE `admin`  (
  `name` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `pwd` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL
) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8mb3 COLLATE = utf8mb3_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of admin
-- ----------------------------
INSERT INTO `admin` VALUES ('admin', 'admin');
INSERT INTO `admin` VALUES ('小明', '111');
INSERT INTO `admin` VALUES ('张三', '123456');
INSERT INTO `admin` VALUES ('root', 'root');
INSERT INTO `admin` VALUES ('fzj', '111');
INSERT INTO `admin` VALUES ('fzj123', '123');
INSERT INTO `admin` VALUES ('fzj1234', '111');
INSERT INTO `admin` VALUES ('168', '168');
INSERT INTO `admin` VALUES ('a1', '111');
INSERT INTO `admin` VALUES ('冯志杰', '123456');
INSERT INTO `admin` VALUES ('李四', '111');
INSERT INTO `admin` VALUES ('陈凯', '111');
INSERT INTO `admin` VALUES ('@@', '111');
INSERT INTO `admin` VALUES ('唐亮', '111');

-- ----------------------------
-- Table structure for late_return_records
-- ----------------------------
DROP TABLE IF EXISTS `late_return_records`;
CREATE TABLE `late_return_records`  (
  `record_id` int NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `student_name` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `dorm_no` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '',
  `late_date` date NOT NULL,
  `is_not_return` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '',
  `created_by` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`record_id`) USING BTREE,
  INDEX `idx_student_id`(`student_id`) USING BTREE,
  INDEX `idx_late_date`(`late_date`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of late_return_records
-- ----------------------------

-- ----------------------------
-- Table structure for signin_records
-- ----------------------------
DROP TABLE IF EXISTS `signin_records`;
CREATE TABLE `signin_records`  (
  `record_id` int NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `student_name` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `dorm_no` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '',
  `sign_time` datetime NOT NULL,
  `location_text` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '',
  `latitude` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '',
  `longitude` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '',
  `operator_name` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '',
  `operator_role` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`record_id`) USING BTREE,
  INDEX `idx_student_id`(`student_id`) USING BTREE,
  INDEX `idx_sign_time`(`sign_time`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of signin_records
-- ----------------------------

-- ----------------------------
-- Table structure for student
-- ----------------------------
DROP TABLE IF EXISTS `student`;
CREATE TABLE `student`  (
  `user` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `id` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `gender` char(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NULL DEFAULT NULL,
  `Dno` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NULL DEFAULT NULL,
  `class` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `id`(`id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8mb3 COLLATE = utf8mb3_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of student
-- ----------------------------
INSERT INTO `student` VALUES ('张三', '100', '男', '201', '15260951190', '21计本2');
INSERT INTO `student` VALUES ('张四', '101', '男', '201', '15260951192', '21软工2');
INSERT INTO `student` VALUES ('张五', '102', '女', '201', '15260951193', '21计本2');
INSERT INTO `student` VALUES ('张六', '103', '男', '202', '15260951194', '21机电2');
INSERT INTO `student` VALUES ('王五', '104', '男', '203', '15100000000', '21服装1');
INSERT INTO `student` VALUES ('李红', '105', '女', '205', '15012345678', '22数控1');
INSERT INTO `student` VALUES ('小赵', '106', '男', '301', '13811112222', '20网工1');
INSERT INTO `student` VALUES ('大三', '111', '男', '222', '2222', '20光电2');
INSERT INTO `student` VALUES ('1112', '1221', '男', '111', '111', '111');
INSERT INTO `student` VALUES ('小明', '31', '男', '501', '1321321313231', '21服饰1');
INSERT INTO `student` VALUES ('周杰', '311933', '女', '515', '1333333333', '21物联网1');
INSERT INTO `student` VALUES ('陈凯', '312', '男', 'A203', '11111111', '20网工1');
INSERT INTO `student` VALUES ('王德发', '31535', '男', '304', '120120', '21国贸1');
INSERT INTO `student` VALUES ('小红', '333', '女', '301', '133333333333', '21金融1');

-- ----------------------------
-- Table structure for wangui
-- ----------------------------
DROP TABLE IF EXISTS `wangui`;
CREATE TABLE `wangui`  (
  `user` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `id` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `Dno` varchar(4) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `date` date NOT NULL,
  `Nback` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `beizu` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8mb3 COLLATE = utf8mb3_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of wangui
-- ----------------------------
INSERT INTO `wangui` VALUES ('冯志杰', '168', '206', '2030-10-10', '否', '555');
INSERT INTO `wangui` VALUES ('11', '22', '33', '2022-01-02', '是', '请假2天');
INSERT INTO `wangui` VALUES ('33', '33', '33', '2021-11-11', '33', '33');
INSERT INTO `wangui` VALUES ('王振', '456', 'a201', '2022-01-10', '是', '旷课');
INSERT INTO `wangui` VALUES ('张亮', '789', 'a101', '2022-05-02', '是', '有事');
INSERT INTO `wangui` VALUES ('张小凡', '789789', '130', '2022-01-02', '是', '请假2天');

SET FOREIGN_KEY_CHECKS = 1;
