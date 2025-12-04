-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 07-11-2025 a las 17:10:36
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `districarnes_navarro`
--
CREATE DATABASE IF NOT EXISTS `districarnes_navarro` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `districarnes_navarro`;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--
-- Creación: 07-11-2025 a las 12:38:59
--

CREATE TABLE `categorias` (
  `id_categoria` int(11) NOT NULL,
  `nombre_categoria` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id_categoria`, `nombre_categoria`) VALUES
(1, 'Cortes Premium Res'),
(2, 'Cortes Corrientes Res'),
(3, 'Costillas Res'),
(4, 'Carne Molida Res'),
(5, 'Vísceras Res'),
(6, 'Huesos Res'),
(7, 'Cortes Premium Cerdo'),
(8, 'Tocinos Cerdo'),
(9, 'Costillas Cerdo'),
(10, 'Pollo Entero'),
(11, 'Partes de Pollo'),
(12, 'Otros');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cliente`
--
-- Creación: 07-11-2025 a las 12:38:59
--

CREATE TABLE `cliente` (
  `id_cliente` int(11) NOT NULL,
  `nombre` varchar(45) NOT NULL,
  `apellido` varchar(45) NOT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contrato_proveedor`
--
-- Creación: 07-11-2025 a las 12:38:59
--

CREATE TABLE `contrato_proveedor` (
  `id_contrato` int(11) NOT NULL,
  `id_proveedor` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `terminos` text NOT NULL,
  `estado` enum('activo','vencido','cancelado') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_venta`
--
-- Creación: 07-11-2025 a las 12:38:59
--

CREATE TABLE `detalle_venta` (
  `id_detalle` int(11) NOT NULL,
  `id_venta` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL CHECK (`cantidad` > 0),
  `precio_unitario` decimal(10,2) NOT NULL CHECK (`precio_unitario` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `entrega`
--
-- Creación: 07-11-2025 a las 12:38:59
--

CREATE TABLE `entrega` (
  `id_entrega` int(11) NOT NULL,
  `id_venta` int(11) NOT NULL,
  `direccion` varchar(255) NOT NULL,
  `estado` enum('pendiente','en camino','entregado','fallido') DEFAULT 'pendiente',
  `fecha_entrega` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lotes`
--
-- Creación: 07-11-2025 a las 12:38:59
--

CREATE TABLE `lotes` (
  `id_lote` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `numero_lote` varchar(50) DEFAULT NULL,
  `fecha_caducidad` date NOT NULL,
  `precio_compra_lote` decimal(10,2) NOT NULL CHECK (`precio_compra_lote` >= 0),
  `descripcion` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificacion`
--
-- Creación: 07-11-2025 a las 12:38:59
--

CREATE TABLE `notificacion` (
  `id_notificacion` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `mensaje` text NOT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `leida` tinyint(1) DEFAULT 0,
  `tipo` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ofertas`
--
-- Creación: 07-11-2025 a las 12:38:59
--

CREATE TABLE `ofertas` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo` enum('percentage','fixed','bogo') NOT NULL DEFAULT 'percentage',
  `valor_descuento` decimal(12,2) NOT NULL DEFAULT 0.00,
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `limite_usos` int(11) DEFAULT NULL,
  `estado` varchar(16) NOT NULL DEFAULT 'inactive',
  `productos_json` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `imagen` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ofertas`
--

INSERT INTO `ofertas` (`id`, `nombre`, `descripcion`, `tipo`, `valor_descuento`, `fecha_inicio`, `fecha_fin`, `limite_usos`, `estado`, `productos_json`, `created_at`, `imagen`) VALUES
(5, 'Lomo fino', 'dededed', 'fixed', 25000.00, '2025-10-08 00:00:00', '0000-00-00 00:00:00', NULL, 'active', NULL, '2025-10-13 21:15:57', '/static/images/offers/offer_68ed6fe3b36b87.33091067.jpeg'),
(6, 'Lomo ancho', 'Lomo ancho', 'bogo', 50.00, '2025-10-08 00:00:00', '0000-00-00 00:00:00', NULL, 'active', NULL, '2025-10-13 22:24:17', '/static/images/offers/offer_68ed7c11b9ded9.28994685.jpeg'),
(7, 'Punta gorda', 'Punta gorda', 'fixed', 20000.00, '2025-10-08 00:00:00', '0000-00-00 00:00:00', NULL, 'active', NULL, '2025-10-13 22:27:31', '/static/images/offers/offer_68ed7cd33af750.44248820.jpeg');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `orders`
--
-- Creación: 07-11-2025 a las 12:38:59
-- Última actualización: 07-11-2025 a las 15:49:14
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `paypal_id` varchar(64) DEFAULT NULL,
  `user_email` varchar(255) DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `status` varchar(32) NOT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `delivery_method` varchar(32) NOT NULL,
  `address_json` text DEFAULT NULL,
  `schedule_json` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `factus_invoice_id` varchar(128) DEFAULT NULL,
  `factus_number` varchar(128) DEFAULT NULL,
  `factus_status` varchar(32) DEFAULT NULL,
  `factus_pdf_url` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `orders`
--

INSERT INTO `orders` (`id`, `paypal_id`, `user_email`, `user_name`, `status`, `total`, `delivery_method`, `address_json`, `schedule_json`, `created_at`, `factus_invoice_id`, `factus_number`, `factus_status`, `factus_pdf_url`) VALUES
(1, '92T721159F630974S', 'comprador@gmail.com', 'comprador', 'COMPLETED', 14000.00, '0', '{\"street\":\"Avenida Calle cartagena SN 23\",\"city\":\"Cartagena De Indias\",\"dept\":\"Bol\\u00edvar\",\"zip\":\"\",\"notes\":\"\"}', '{\"envio1\":\"sabado\",\"envio2\":\"lunes\"}', '2025-10-13 11:27:21', NULL, NULL, NULL, NULL),
(2, '6DS76703S2114415R', 'comprador@gmail.com', 'comprador', 'COMPLETED', 45000.00, '0', '{\"street\":\"Avenida Calle cartagena SN 23\",\"city\":\"Cartagena De Indias\",\"dept\":\"Bol\\u00edvar\",\"zip\":\"\",\"notes\":\"\"}', '{\"envio1\":\"sabado\",\"envio2\":\"lunes\"}', '2025-10-13 14:41:57', NULL, NULL, NULL, NULL),
(3, '00V78098FH534732J', 'juanpiayola@gmail.com', 'juan pablo', 'PROCESSING', 46000.00, '0', '{\"street\":\"Avenida Calle cartagena SN 23\",\"city\":\"Cartagena De Indias\",\"dept\":\"Bol\\u00edvar\",\"zip\":\"\",\"notes\":\"\"}', '{\"envio1\":\"sabado\",\"envio2\":\"lunes\"}', '2025-10-13 14:52:00', NULL, NULL, NULL, NULL),
(4, '97598458EK347352A', 'juanpiayola@gmail.com', 'juan pablo', 'PENDING', 14000.00, '0', '{\"street\":\"Avenida Calle cartagena SN 23\",\"city\":\"Cartagena De Indias\",\"dept\":\"Bol\\u00edvar\",\"zip\":\"\",\"notes\":\"\"}', '{\"envio1\":\"sabado\",\"envio2\":\"lunes\"}', '2025-10-13 16:26:25', NULL, NULL, NULL, NULL),
(5, '06U33077V6744505L', 'comprador@gmail.com', 'comprador', '0', 14000.00, '0', '{\"street\":\"Avenida Calle cartagena SN 23\",\"city\":\"Cartagena De Indias\",\"dept\":\"Bol\\u00edvar\",\"zip\":\"\",\"notes\":\"\"}', '{\"envio1\":\"sabado\",\"envio2\":\"lunes\"}', '2025-11-07 15:49:14', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `order_items`
--
-- Creación: 07-11-2025 a las 12:38:59
-- Última actualización: 07-11-2025 a las 15:49:14
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `qty` int(11) NOT NULL DEFAULT 1,
  `image` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `title`, `price`, `qty`, `image`) VALUES
(1, 1, 'Paticas de pollo', 14000.00, 1, 'http://localhost/DISTRICARNES/static/images/products/prod_68ebf800090820.22430874.jpg'),
(2, 2, 'Paticas de pollo', 14000.00, 1, 'http://localhost/DISTRICARNES/static/images/products/prod_68ebf800090820.22430874.jpg'),
(3, 2, 'Alas', 15000.00, 1, 'http://localhost/DISTRICARNES/static/images/products/prod_68ebf7ee45ce63.22704605.webp'),
(4, 2, 'Muslo y contramuslo', 16000.00, 1, 'http://localhost/DISTRICARNES/static/images/products/prod_68ebf7c3b4e027.67202182.jpeg'),
(5, 3, 'Paticas de pollo', 14000.00, 1, 'http://localhost/DISTRICARNES/static/images/products/prod_68ebf800090820.22430874.jpg'),
(6, 3, 'Alas', 15000.00, 1, 'http://localhost/DISTRICARNES/static/images/products/prod_68ebf7ee45ce63.22704605.webp'),
(7, 3, 'Alas con costillar', 17000.00, 1, 'http://localhost/DISTRICARNES/static/images/products/prod_68ebf7d3524a86.07522593.webp'),
(8, 4, 'Paticas de pollo', 14000.00, 1, 'http://localhost/DISTRICARNES/static/images/products/prod_68ebf800090820.22430874.jpg'),
(9, 5, 'Paticas de pollo', 14000.00, 1, 'http://localhost/DISTRICARNES/static/images/products/prod_68ebf800090820.22430874.jpg');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `password_resets`
--
-- Creación: 07-11-2025 a las 12:38:59
-- Última actualización: 07-11-2025 a las 13:57:56
--

CREATE TABLE `password_resets` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token_hash`, `expires_at`, `used`, `created_at`) VALUES
(1, 8, '602239ae462bfb8b7dd9d797f877b506ec360809fa2b9ba4b4774d2bbd9b7acf', '2025-10-04 15:03:38', 0, '2025-10-04 12:33:38'),
(2, 8, '2d1ab7f3d4d26b989014a13c27d2bd7e199b641b807f11643a8672e32d789951', '2025-10-04 15:24:22', 0, '2025-10-04 12:54:22'),
(3, 8, '131e7271bed5ef66d4de1563cb8d2907fa3df670c57cc8e7280e5618ba162ec2', '2025-10-04 15:32:01', 0, '2025-10-04 13:02:01'),
(4, 8, '17417dbcbc86c4f2b9f837c3ec234e355ebee7b68ad99afd5cd739e551b20845', '2025-10-04 15:32:02', 0, '2025-10-04 13:02:02'),
(5, 8, 'f72f41984c78b6b08f1f84af2b0c2c50273b116ff22442ed098244d83efc91a2', '2025-10-04 15:33:45', 0, '2025-10-04 13:03:45'),
(6, 8, '7b1b4c01ec1de30d6c39ec24aaf3fc9f13262f6a361fea1efa9d47c1d3c5feeb', '2025-10-04 15:34:08', 0, '2025-10-04 13:04:08'),
(7, 8, '72af508b50cab58582374acfd493680983210093d016ce340dd9c189accb035d', '2025-10-04 15:42:30', 0, '2025-10-04 13:12:30'),
(8, 8, '7dba16bea35564ab5f6f3c627f556d3a9dfa59b89f59a8bd959667b45395f5a8', '2025-10-04 15:46:09', 0, '2025-10-04 13:16:09'),
(9, 8, 'f5ca2976fbccb5554ac428f39c31cd5471da20fd90a52d04a1c3ea0c2190e10e', '2025-10-04 15:50:16', 0, '2025-10-04 13:20:16'),
(10, 8, '516a6e84dda58199920d81204675740ad10bf17f209ed60093e646134c664194', '2025-10-04 15:53:55', 0, '2025-10-04 13:23:55'),
(11, 8, 'aefe3a4c5f55171a86e44c2eee903e447e1056086ddf0c7359822eb4d93dd00f', '2025-10-04 15:59:35', 0, '2025-10-04 13:29:35'),
(12, 8, '98c5f96a8e4be0cb6664826e2c8be2f741d61357a7045485397738b72303b040', '2025-10-04 16:07:37', 0, '2025-10-04 13:37:37'),
(13, 8, '243bb91adfea113d3769f6461c16e9c5b5ecc805526a97f336feb775c54115a1', '2025-10-04 16:10:21', 0, '2025-10-04 13:40:21'),
(14, 8, 'bfa367c7923586c79a663d528037641ba04067506f2a5833ce630496942d6e6a', '2025-10-04 16:11:24', 0, '2025-10-04 13:41:24'),
(15, 8, '3f4accf86cbc925314824ee047d187b6b26b224b0a6e717f58622212bf66277e', '2025-10-04 16:13:43', 0, '2025-10-04 13:43:43'),
(16, 8, 'a2f9bffd89c66ebfdf332de41c71cdb0bbb41493d1a2a807192179d4560569da', '2025-10-04 16:22:26', 0, '2025-10-04 13:52:26'),
(17, 9, '0ebfe015114867d6366aa1b5f97f14ca0b431a2fe8a46f1bd5d6325e23ede89e', '2025-10-04 16:40:19', 1, '2025-10-04 14:10:19'),
(18, 8, '5601d428b0543442d918659911587d9d7f2f033ab8fd523686de459ba488fa9e', '2025-10-05 01:36:41', 0, '2025-10-04 23:06:41'),
(19, 8, '2dad845f01d9a67b10b78945b852157863f4f668287e87c970860e1278e1f2d7', '2025-10-08 14:48:39', 0, '2025-10-08 12:18:39'),
(20, 8, '86388a81b431223abacc25884385b5eee62e5f7ccbaa1d58888341d3e2d7e41d', '2025-10-08 15:30:41', 0, '2025-10-08 13:00:41'),
(21, 9, '6b8b01319b8737ce4dd97816ad9306317c3c44544970d7e506e82100f8e53d73', '2025-10-13 17:14:24', 1, '2025-10-13 14:44:24'),
(22, 10, 'a7fd42fa4dd9f770dfa173f7061c111345582bc361bcdd218e2fce9d9b1f100e', '2025-11-07 15:01:57', 0, '2025-11-07 13:31:57'),
(23, 10, '8ef513b05e49e640a277b598f6a858cab76b230ef5acef18e19a672ca118daa2', '2025-11-07 15:09:12', 0, '2025-11-07 13:39:12'),
(24, 10, 'eee46b9d95c3c7d22d87cc41b6116a500b4f038d55cf14f3c28faca03d612c2b', '2025-11-07 15:16:05', 0, '2025-11-07 13:46:05'),
(25, 10, '9a05f6a4915c158605ec414d3f59266483b40ccbff026e12546015a864cbd902', '2025-11-07 15:24:53', 0, '2025-11-07 13:54:53'),
(26, 10, 'd9050a4c8f2ba4c84dd879c8f9354b73508796e38e854f8d29836bd23ec635a3', '2025-11-07 15:26:00', 0, '2025-11-07 13:56:00'),
(27, 10, 'c6fad19de1352b9fe1cc8362152bc33636144ecdb6112b435d9d4ff277a21e83', '2025-11-07 15:27:50', 0, '2025-11-07 13:57:50'),
(28, 10, '9cdc400c04112b837da00546ca5bf51ba14d41bfdbf3dc47e8a63165231163d1', '2025-11-07 15:27:56', 0, '2025-11-07 13:57:56');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `preferencia_cliente`
--
-- Creación: 07-11-2025 a las 12:38:59
--

CREATE TABLE `preferencia_cliente` (
  `id_preferencia` int(11) NOT NULL,
  `id_cliente` int(11) NOT NULL,
  `categoria` varchar(50) NOT NULL,
  `valor` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto`
--
-- Creación: 07-11-2025 a las 12:38:59
--

CREATE TABLE `producto` (
  `id_producto` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `precio_venta` decimal(10,2) NOT NULL CHECK (`precio_venta` >= 0),
  `precio_abastecimiento` decimal(10,2) NOT NULL CHECK (`precio_abastecimiento` >= 0),
  `precio_compra_lote` decimal(10,2) DEFAULT NULL COMMENT 'Precio de compra del lote',
  `descripcion` text DEFAULT NULL COMMENT 'Descripción',
  `fecha_caducidad` date DEFAULT NULL,
  `numero_lote` varchar(32) DEFAULT NULL COMMENT 'Número de Lote (opcional)',
  `stock` int(11) NOT NULL DEFAULT 0 CHECK (`stock` >= 0),
  `id_proveedor` int(11) NOT NULL,
  `id_categoria` int(11) DEFAULT NULL,
  `imagen_producto` varchar(255) DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `producto`
--

INSERT INTO `producto` (`id_producto`, `nombre`, `precio_venta`, `precio_abastecimiento`, `precio_compra_lote`, `descripcion`, `fecha_caducidad`, `numero_lote`, `stock`, `id_proveedor`, `id_categoria`, `imagen_producto`, `estado`) VALUES
(1, 'Lomo fino', 25000.00, 18000.00, 300000.00, 'dededed', '2025-10-07', 'L20251012-CC3543', 1, 1, 1, '/static/images/products/prod_68ebea69caa298.78139733.jpeg', 1),
(2, 'Lomo ancho', 24000.00, 17500.00, 500000.00, 'Segundo producto que se atualiza \r\n', '2025-10-05', 'L20251012-56CAF3', 9, 1, 1, '/static/images/products/prod_68ebea8dc8ae31.97147879.jpeg', 1),
(3, 'Punta gorda', 22000.00, 16000.00, 50000.00, '', '2025-10-05', 'L20251012-05F912', 50, 1, 1, '/static/images/products/prod_68ebeaf6726954.44629935.jpeg', 1),
(4, 'Palomilla', 23000.00, 16500.00, 400000.00, '', '2025-10-05', 'L20251012-B75F68', 50, 1, 1, '/static/images/products/prod_68ebf31d3354e0.79006117.jpeg', 1),
(5, 'Sobre barriga', 21000.00, 15000.00, 300000.00, '', '2025-10-05', 'L20251012-B8A126', 50, 1, 1, '/static/images/products/prod_68ebf35317a883.94345415.jpg', 1),
(6, 'Espaldilla', 20000.00, 14500.00, 2.00, '', '2025-10-05', 'L20251012-CE77CF', 50, 1, 1, '/static/images/products/prod_68ebf364ced524.41922891.webp', 1),
(7, 'Barcino', 19000.00, 14000.00, 2.00, '', '2025-10-05', 'L20251012-1765D5', 50, 1, 1, '/static/images/products/prod_68ebf37ec581a7.64419766.jpg', 1),
(8, 'Posta (res)', 22500.00, 16000.00, 3.00, '', '2025-10-05', 'L20251012-C444FC', 50, 1, 1, '/static/images/products/prod_68ebf39b35b5b5.35756584.png', 1),
(9, 'Bollito (res)', 21500.00, 15500.00, 2.00, '', '2025-10-05', 'L20251012-A9D261', 50, 1, 1, '/static/images/products/prod_68ebf4c192d6c9.50344778.webp', 1),
(10, 'Masa frente', 15000.00, 10000.00, 2.00, '', '2025-10-05', 'L20251012-55D311', 50, 1, 2, '/static/images/products/prod_68ebf4df8f9e44.65315709.jpeg', 1),
(11, 'Masa chocozuela', 14500.00, 9500.00, 1.00, '', '2025-10-05', 'L20251012-AE985E', 50, 1, 2, '/static/images/products/prod_68ebf57edbb168.75066028.jpeg', 1),
(12, 'Morro', 13000.00, 9000.00, 1.00, '', '2025-10-05', 'L20251012-0D52BC', 50, 1, 2, '/static/images/products/prod_68ebf596e82cd2.65883959.jpg', 1),
(13, 'Cohete', 14000.00, 9500.00, 3.00, '', '2025-10-05', 'L20251012-F875AB', 50, 1, 2, '/static/images/products/prod_68ebf5ab48f442.24850910.jpeg', 1),
(14, 'Corriente', 13500.00, 9000.00, 3.00, '', '2025-10-05', 'L20251012-9E8B8B', 50, 1, 2, '/static/images/products/prod_68ebf5bd857667.96149222.webp', 1),
(15, 'Costilla corriente', 18000.00, 12000.00, 3.00, '', '2025-10-05', 'L20251012-6A684B', 50, 1, 3, '/static/images/products/prod_68ebf5cdc66315.94191505.webp', 1),
(16, 'Costilla especial', 20000.00, 13500.00, 3.00, '', '2025-10-05', 'L20251012-E2DD4B', 50, 1, 3, '/static/images/products/prod_68ebf5e4dafd05.90954993.jpeg', 1),
(17, 'Costilla super', 22000.00, 15000.00, 5.00, '', '2025-10-05', 'L20251012-71E74D', 50, 1, 3, '/static/images/products/prod_68ebf5f5e94d85.96218916.webp', 1),
(18, 'Molida corriente', 16000.00, 11000.00, 1.00, '', '2025-10-05', 'L20251012-36CCC8', 50, 1, 4, '/static/images/products/prod_68ebf567853f14.99177535.jpg', 1),
(19, 'Molida especial', 18000.00, 12500.00, 5.00, '', '2025-10-05', 'L20251012-813B04', 50, 1, 4, '/static/images/products/prod_68ebf609d26710.61829850.jpeg', 1),
(20, 'Molida super', 20000.00, 14000.00, 20000.00, '', '2025-10-05', 'L20251012-A72061', 50, 1, 4, '/static/images/products/prod_68ebf61a7cc520.77777105.jpg', 1),
(21, 'Hígado', 12000.00, 8000.00, 3000000.00, '', '2025-10-05', 'L20251012-0B7DB8', 50, 1, 5, '/static/images/products/prod_68ebf6370ee2a9.09330560.jpeg', 1),
(22, 'Bofe', 11000.00, 7500.00, 3.00, '', '2025-10-05', 'L20251012-ADA48D', 50, 1, 5, '/static/images/products/prod_68ebf64f2430f4.02541214.jpg', 1),
(23, 'Corazón', 13000.00, 8500.00, 32.00, '', '2025-10-05', 'L20251012-07DEE5', 50, 1, 5, '/static/images/products/prod_68ebf6676c3a31.82693452.webp', 1),
(24, 'Lengua', 25000.00, 17000.00, 2000000.00, '', '2025-10-05', 'L20251012-9075AE', 50, 1, 5, '/static/images/products/prod_68ebf67dc34327.50751803.png', 1),
(25, 'Riñón', 14000.00, 9000.00, 1.00, '', '2025-10-05', 'L20251012-0F2FAB', 50, 1, 5, '/static/images/products/prod_68ebf68eecd784.84489479.jpg', 1),
(26, 'Rabo de res', 23000.00, 16000.00, 2.00, '', '2025-10-05', 'L20251012-31166F', 50, 1, 5, '/static/images/products/prod_68ebf69d90ca12.57469540.jpeg', 1),
(27, 'Carne desmechar', 16500.00, 11000.00, 1.00, '', '2025-10-05', 'L20251012-CE8B6B', 50, 1, 12, '/static/images/products/prod_68ebf6ad64e1c5.06929708.jpg', 1),
(28, 'Bistec', 24000.00, 17000.00, 1.00, '', '2025-10-05', 'L20251012-3D74AE', 50, 1, 1, '/static/images/products/prod_68ebf6bf6866b0.80022685.png', 1),
(29, 'Huesos', 8000.00, 5000.00, 0.97, '', '2025-10-05', 'L20251012-98F9FC', 50, 1, 6, '/static/images/products/prod_68ebf6d1c7ef39.46405632.webp', 1),
(30, 'Lomo de cerdo', 20000.00, 14000.00, 1.00, '', '2025-10-05', 'L20251012-A0AE13', 50, 1, 7, '/static/images/products/prod_68ebf6e13735c0.77130189.jpg', 1),
(31, 'Solomito de cerdo', 22000.00, 15000.00, 1000000.00, '', '2025-10-05', 'L20251012-4693C8', 50, 1, 7, '/static/images/products/prod_68ebf7022365c7.34699182.jpg', 1),
(32, 'Chuleta especial', 21000.00, 14500.00, 1.00, '', '2025-10-05', 'L20251012-2481D0', 50, 1, 7, '/static/images/products/prod_68ebf711d5cf91.15905289.jpg', 1),
(33, 'Masa de cerdo', 15000.00, 10000.00, 0.96, '', '2025-10-05', 'L20251012-18C25A', 50, 1, 12, '/static/images/products/prod_68ebf7241b6910.98394274.jpeg', 1),
(34, 'Chuleta corriente', 16000.00, 11000.00, NULL, NULL, '2025-10-05', NULL, 50, 1, 12, '/static/images/products/prod_chuleta-corriente_68ebe9c0e63c9.jpg', 1),
(35, 'Contra codillo', 17000.00, 11500.00, 1.00, '', '2025-10-05', 'L20251012-09AAC9', 50, 1, 12, '/static/images/products/prod_68ebf73d9d8269.18658443.jpg', 1),
(36, 'Patica', 14000.00, 9500.00, 1.97, '', '2025-10-05', 'L20251012-15C265', 50, 1, 12, '/static/images/products/prod_68ebf750d23302.21533921.jpeg', 1),
(37, 'Tocino carnudo', 18000.00, 12000.00, 1.00, '', '2025-10-05', 'L20251012-55F1D8', 50, 1, 8, '/static/images/products/prod_68ebf763af4369.37205273.jpg', 1),
(38, 'Tocino corriente', 16000.00, 10500.00, 1.00, '', '2025-10-05', 'L20251012-985DEB', 50, 1, 8, '/static/images/products/prod_68ebf7749be327.68650889.jpeg', 1),
(39, 'Papada', 15000.00, 10000.00, 1.14, '', '2025-10-05', 'L20251012-40D022', 30, 1, 8, '/static/images/products/prod_68ebf788530097.91516183.webp', 1),
(40, 'Costilla de cerdo', 19000.00, 13000.00, NULL, NULL, '2025-10-05', NULL, 50, 1, 9, '/static/images/products/prod_costilla-de-cerdo_68ebe9c0ebcd8.jpeg', 1),
(41, 'Biceras', 17000.00, 11000.00, 2.00, '', '2025-10-05', 'L20251012-0F77F9', 50, 1, 12, '/static/images/products/prod_68ebf79a9c4a95.02257713.jpeg', 1),
(42, 'Pollo entero', 18000.00, 12000.00, 1.00, '', '2025-10-05', 'L20251012-AE9398', 50, 1, 10, '/static/images/products/prod_68ebf7af637650.11955032.jpg', 1),
(43, 'Muslo y contramuslo', 16000.00, 10500.00, 1.00, '', '2025-10-05', 'L20251012-CEAF3A', 50, 1, 11, '/static/images/products/prod_68ebf7c3b4e027.67202182.jpeg', 1),
(44, 'Alas con costillar', 17000.00, 11000.00, 1.00, '', '2025-10-05', 'L20251012-B62F7D', 50, 1, 11, '/static/images/products/prod_68ebf7d3524a86.07522593.webp', 1),
(45, 'Alas', 15000.00, 9500.00, 1.94, '', '2025-10-05', 'L20251012-9D507B', 50, 1, 11, '/static/images/products/prod_68ebf7ee45ce63.22704605.webp', 1),
(46, 'Paticas de pollo', 14000.00, 9000.00, 1.00, '', '2025-10-05', 'L20251012-C7CF14', 50, 1, 11, '/static/images/products/prod_68ebf800090820.22430874.jpg', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedor`
--
-- Creación: 07-11-2025 a las 12:38:59
--

CREATE TABLE `proveedor` (
  `id_proveedor` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `contacto` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--
-- Creación: 07-11-2025 a las 12:38:59
-- Última actualización: 07-11-2025 a las 15:45:14
--

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL,
  `nombres_completos` varchar(255) NOT NULL,
  `cedula` varchar(50) NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `celular` varchar(20) DEFAULT NULL,
  `correo_electronico` varchar(100) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `rol` enum('admin','trabajo') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id_usuario`, `nombres_completos`, `cedula`, `direccion`, `celular`, `correo_electronico`, `contrasena`, `rol`, `created_at`, `updated_at`) VALUES
(7, 'comprador', '12234', 'cr 11b 17-95', '23232123131', 'comprador@gmail.com', '$2y$10$CkjAtl.mVsvK4i2A5pV8rubPTcABmETvyIpn68N2rua61F1Qg392C', 'trabajo', '2025-10-04 12:20:51', '2025-10-04 14:18:00'),
(8, 'JUAN HUMBERTO VEGA SANCHEZ', '1052732445', 'CR 11B 17-95 VILLANUEVA BOLIVAR', '3108392866', 'juanhumbertovega600@gmail.com', '$2y$10$edAtp.uQEd5hOOK/c/WSXu05Lwyp0Idttx13XaTHbv5nDnvZ8lrKS', 'admin', '2025-10-04 12:24:59', '2025-10-04 12:25:13'),
(9, 'juan pablo', '23234234', '', '32323442342', 'juanpiayola@gmail.com', '$2y$10$6MdcyqCHJAv0Wzme2yPrZ.GXcOXnEoVMNkZa1ApNG5CV0ov0y2TY6', 'trabajo', '2025-10-04 14:09:35', '2025-10-13 14:45:55'),
(10, 'unity', '65432151', 'cartegena', '3105246873', 'unityaccess56@gmail.com', '$2y$10$iA47rfsE3jOW8VcJJDJFeOhZfOgRrUFIPVPMaSKwpqEphSaDc6GxC', 'trabajo', '2025-11-07 13:30:53', '2025-11-07 15:45:14');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `venta`
--
-- Creación: 07-11-2025 a las 12:38:59
--

CREATE TABLE `venta` (
  `id_venta` int(11) NOT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `total` decimal(10,2) NOT NULL CHECK (`total` >= 0),
  `id_cliente` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `metodo_pago` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id_categoria`);

--
-- Indices de la tabla `cliente`
--
ALTER TABLE `cliente`
  ADD PRIMARY KEY (`id_cliente`),
  ADD UNIQUE KEY `correo` (`correo`);

--
-- Indices de la tabla `contrato_proveedor`
--
ALTER TABLE `contrato_proveedor`
  ADD PRIMARY KEY (`id_contrato`),
  ADD KEY `id_proveedor` (`id_proveedor`);

--
-- Indices de la tabla `detalle_venta`
--
ALTER TABLE `detalle_venta`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_venta` (`id_venta`),
  ADD KEY `id_producto` (`id_producto`);

--
-- Indices de la tabla `entrega`
--
ALTER TABLE `entrega`
  ADD PRIMARY KEY (`id_entrega`),
  ADD KEY `id_venta` (`id_venta`);

--
-- Indices de la tabla `lotes`
--
ALTER TABLE `lotes`
  ADD PRIMARY KEY (`id_lote`),
  ADD UNIQUE KEY `uk_producto_lote` (`id_producto`,`numero_lote`),
  ADD KEY `idx_lotes_producto` (`id_producto`),
  ADD KEY `idx_lotes_caducidad` (`fecha_caducidad`);

--
-- Indices de la tabla `notificacion`
--
ALTER TABLE `notificacion`
  ADD PRIMARY KEY (`id_notificacion`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `ofertas`
--
ALTER TABLE `ofertas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indices de la tabla `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash_idx` (`token_hash`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `preferencia_cliente`
--
ALTER TABLE `preferencia_cliente`
  ADD PRIMARY KEY (`id_preferencia`),
  ADD KEY `id_cliente` (`id_cliente`);

--
-- Indices de la tabla `producto`
--
ALTER TABLE `producto`
  ADD PRIMARY KEY (`id_producto`),
  ADD KEY `id_proveedor` (`id_proveedor`),
  ADD KEY `fk_categoria` (`id_categoria`);

--
-- Indices de la tabla `proveedor`
--
ALTER TABLE `proveedor`
  ADD PRIMARY KEY (`id_proveedor`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `cedula` (`cedula`),
  ADD UNIQUE KEY `correo_electronico` (`correo_electronico`),
  ADD UNIQUE KEY `uk_cedula` (`cedula`),
  ADD UNIQUE KEY `uk_correo` (`correo_electronico`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `lotes`
--
ALTER TABLE `lotes`
  MODIFY `id_lote` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ofertas`
--
ALTER TABLE `ofertas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de la tabla `producto`
--
ALTER TABLE `producto`
  MODIFY `id_producto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `lotes`
--
ALTER TABLE `lotes`
  ADD CONSTRAINT `fk_lotes_producto` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id_producto`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `producto`
--
ALTER TABLE `producto`
  ADD CONSTRAINT `fk_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
