# Aviar - Sistema de Gestión Avícola

Sistema completo de gestión para granjas avícolas desarrollado en PHP.

## Características

- 🐔 Gestión de inventario aviar
- 📊 Reportes y análisis de producción
- 💉 Control de vacunación y salud
- 📈 Seguimiento de alimentación y pesaje
- 💰 Control de ventas y compras
- 🥚 Registro de producción de huevos
- 📱 Interfaz moderna y responsiva

## Requisitos del Sistema

- PHP 7.4 o superior
- MySQL/MariaDB
- Apache Server (XAMPP recomendado)
- PDO PHP Extension

## Instalación

1. Clonar el repositorio en tu directorio XAMPP:
```bash
cd C:\xampp\htdocs
git clone https://github.com/dzambrano1/aviar.git
```

2. Configurar la base de datos:
   - Importar el esquema de base de datos desde `/sql/`
   - Actualizar credenciales en `pdo_conexion.php`

3. Iniciar XAMPP y acceder a:
```
http://localhost/aviar
```

## Estructura del Proyecto

- `/includes/` - Archivos de configuración e inclusiones
- `/images/` - Recursos gráficos
- `/reports/` - Reportes generados
- `/sql/` - Scripts de base de datos
- `/uploads/` - Archivos subidos por usuarios

## Tecnologías Utilizadas

- PHP
- MySQL/PDO
- JavaScript
- CSS3
- FPDF (Generación de PDFs)
- Chart.js (Gráficos y visualizaciones)

## Autor

**dzambrano1**

## Licencia

Todos los derechos reservados © 2025

