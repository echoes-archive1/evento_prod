<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/middleware/Auth.php';

// Get event ID
$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$event_id) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Get event details
$db = Database::getInstance()->getConnection();

$event_sql = "
    SELECT e.*, c.club_name, u.full_name as creator_name
    FROM events e
    LEFT JOIN clubs c ON e.club_id = c.id
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.id = :event_id AND e.status = 'approved'
";
$event_stmt = $db->prepare($event_sql);
$event_stmt->execute([':event_id' => $event_id]);
$event = $event_stmt->fetch();

if (!$event) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = $event['event_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/alerts.css">
    <style>
        .public-navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: clamp(60px, 8vh, 80px);
            background: rgba(10, 10, 10, 0.4);
            backdrop-filter: blur(40px) saturate(150%);
            -webkit-backdrop-filter: blur(40px) saturate(150%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 
                0 4px 30px rgba(0, 0, 0, 0.5),
                0 0 0 1px rgba(255, 255, 255, 0.03) inset;
            display: flex;
            align-items: center;
            padding: 0 clamp(20px, 4vw, 60px);
            z-index: 1000;
        }
        
        .back-button {
            padding: clamp(10px, 1.5vw, 14px) clamp(20px, 3vw, 28px);
            border-radius: clamp(8px, 1.2vw, 12px);
            font-size: clamp(0.9rem, 1.8vw, 1.1rem);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: clamp(8px, 1.5vw, 12px);
            background: rgba(139, 92, 246, 0.1);
            color: #e0e7ff;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }
        
        .back-button:hover {
            background: rgba(139, 92, 246, 0.2);
            border-color: rgba(168, 85, 247, 0.5);
            transform: translateX(-3px);
            box-shadow: 0 4px 20px rgba(139, 92, 246, 0.3);
        }
        
        .public-main {
            margin-top: clamp(60px, 8vh, 80px);
            padding: clamp(10px, 2vw, 40px);
            min-height: calc(100vh - clamp(60px, 8vh, 80px));
            width: 100%;
            box-sizing: border-box;
            position: relative;
        }
        
        .public-main::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #000000;
            z-index: -2;
        }
        
        .public-main::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 50%, rgba(139, 92, 246, 0.05) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(236, 72, 153, 0.05) 0%, transparent 50%),
                        radial-gradient(circle at 40% 20%, rgba(59, 130, 246, 0.05) 0%, transparent 50%);
            animation: radialMove 25s ease infinite;
            z-index: -1;
        }
        
        @keyframes pageGradientMove {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        @keyframes radialMove {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.1); }
        }
        
        .event-detail-container {
            max-width: 100%;
            width: 100%;
            margin: 0;
            padding: 0;
            display: grid;
            grid-template-columns: 2fr 1.5fr;
            gap: clamp(15px, 4vw, 60px);
            align-items: start;
            box-sizing: border-box;
        }
        
        .event-left-section {
            display: flex;
            flex-direction: column;
            gap: clamp(1.5rem, 3vh, 2.5rem);
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
        }
        
        .event-right-section {
            position: sticky;
            top: calc(clamp(60px, 8vh, 80px) + 20px);
            width: 100%;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: clamp(1rem, 2vh, 2rem);
            box-sizing: border-box;
        }
        
        .event-banner {
            width: 100%;
            height: clamp(300px, 40vh, 500px);
            border-radius: clamp(12px, 1.5vw, 20px);
            background-size: cover;
            background-position: center;
            margin-bottom: 0;
            position: relative;
            border: 1px solid rgba(139, 92, 246, 0.2);
            transition: all 0.3s ease;
        }
        
        .event-banner:hover {
            border-color: rgba(168, 85, 247, 0.5);
            box-shadow: 0 10px 40px rgba(139, 92, 246, 0.4);
        }
        
        .event-header {
            text-align: left;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        
        .event-badge {
            display: inline-block;
            padding: clamp(4px, 0.6vw, 8px) clamp(12px, 1.8vw, 20px);
            background: rgba(99, 102, 241, 0.2);
            color: #c4b5fd;
            border-radius: 1.5rem;
            font-size: clamp(0.8rem, 1.6vw, 1rem);
            font-weight: 700;
            margin-bottom: clamp(0.75rem, 2vh, 1.25rem);
            border: 1px solid rgba(139, 92, 246, 0.3);
            letter-spacing: 0.03em;
            transition: all 0.3s ease;
        }
        
        .event-title {
            font-size: clamp(1.75rem, 4.5vw, 3rem);
            font-weight: 900;
            color: #f0f9ff;
            margin-bottom: clamp(1rem, 2vh, 1.5rem);
            text-align: left;
            line-height: 1.2;
            text-shadow: 0 2px 10px rgba(139, 92, 246, 0.2);
        }
        
        .event-meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: clamp(1rem, 2.5vw, 2rem);
            margin-bottom: 0;
            justify-content: flex-start;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: clamp(8px, 1vw, 12px);
            color: rgba(255, 255, 255, 0.7);
            font-size: clamp(0.85rem, 1.8vw, 1.1rem);
        }
        
        .meta-item svg {
            color: #6366f1;
            width: clamp(16px, 2vw, 22px);
            height: clamp(16px, 2vw, 22px);
        }
        
        .event-content-section {
            background: rgba(15, 23, 42, 0.3);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: clamp(12px, 1.5vw, 20px);
            padding: clamp(1.5rem, 3vw, 2.5rem);
            margin: 0;
            text-align: left;
            width: 100%;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }
        
        .event-content-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, 
                rgba(59, 130, 246, 0.08) 0%,
                rgba(139, 92, 246, 0.08) 50%,
                rgba(236, 72, 153, 0.08) 100%);
            background-size: 300% 300%;
            animation: sectionGradientMove 15s ease infinite;
            opacity: 0.7;
            z-index: 0;
        }
        
        .event-content-section > * {
            position: relative;
            z-index: 1;
        }
        
        @keyframes sectionGradientMove {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .event-content-section:hover {
            border-color: rgba(168, 85, 247, 0.6);
            background: rgba(15, 23, 42, 0.8);
            box-shadow: 0 10px 35px rgba(139, 92, 246, 0.4),
                        0 0 30px rgba(168, 85, 247, 0.2);
        }
        
        .event-content-section:hover::before {
            opacity: 1;
            animation-duration: 8s;
        }
        
        .section-title {
            font-size: clamp(1.25rem, 2.5vw, 1.75rem);
            font-weight: 800;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0e7ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: clamp(0.75rem, 2vh, 1.25rem);
            text-align: left;
        }
        
        .section-content {
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.8;
            font-size: clamp(0.9rem, 1.8vw, 1.1rem);
            text-align: left;
        }
        
        .section-content p {
            margin: clamp(0.5rem, 1.5vh, 1rem) 0;
            text-align: left;
        }
        
        .event-stats {
            display: flex;
            flex-direction: column;
            gap: clamp(12px, 2vw, 16px);
            margin: 0;
            width: 100%;
        }
        
        .stat-box {
            background: rgba(15, 23, 42, 0.3);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: clamp(10px, 1.5vw, 16px);
            padding: clamp(1.5rem, 3vw, 2.5rem);
            text-align: center;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .stat-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, 
                rgba(236, 72, 153, 0.08) 0%,
                rgba(139, 92, 246, 0.08) 50%,
                rgba(59, 130, 246, 0.08) 100%);
            background-size: 300% 300%;
            animation: statBoxGradientMove 12s ease infinite;
            opacity: 0.7;
            z-index: 0;
        }
        
        .stat-box > * {
            position: relative;
            z-index: 1;
        }
        
        @keyframes statBoxGradientMove {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .stat-box:hover {
            transform: translateY(-4px) scale(1.03);
            border-color: rgba(168, 85, 247, 0.6);
            background: rgba(15, 23, 42, 0.8);
            box-shadow: 0 12px 40px rgba(139, 92, 246, 0.5),
                        0 0 40px rgba(236, 72, 153, 0.3);
        }
        
        .stat-box:hover::before {
            opacity: 1;
            animation-duration: 6s;
        }
        
        .stat-value {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 900;
            background: linear-gradient(135deg, #60a5fa 0%, #a78bfa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: clamp(0.5rem, 1.5vh, 0.75rem);
        }
        
        .stat-label {
            font-size: clamp(0.85rem, 1.8vw, 1.1rem);
            color: rgba(255, 255, 255, 0.6);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 500;
        }
        
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: clamp(10px, 1.5vw, 12px);
            margin: 0;
            width: 100%;
        }
        
        .btn-large {
            padding: clamp(16px, 2vw, 20px) clamp(24px, 3vw, 32px);
            border-radius: clamp(10px, 1.5vw, 16px);
            font-size: clamp(1rem, 2vw, 1.15rem);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: clamp(8px, 1.2vw, 12px);
            border: none;
            cursor: pointer;
            white-space: nowrap;
            width: 100%;
        }
        
        .btn-large svg {
            width: clamp(16px, 2vw, 22px);
            height: clamp(16px, 2vw, 22px);
        }
        
        .btn-primary-large {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
            color: white;
            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.5);
        }
        
        .btn-primary-large:hover {
            background: linear-gradient(135deg, #7c3aed 0%, #a855f7 50%, #c026d3 100%);
            transform: translateY(-3px);
            box-shadow: 0 10px 35px rgba(139, 92, 246, 0.7);
        }
        
        .btn-secondary-large {
            background: rgba(139, 92, 246, 0.1);
            color: #e0e7ff;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }
        
        .btn-secondary-large:hover {
            background: rgba(139, 92, 246, 0.2);
            border-color: rgba(168, 85, 247, 0.5);
            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.3);
        }
        
        /* Portrait/Vertical orientation */
        @media (orientation: portrait) {
            .public-navbar {
                padding: 0 clamp(10px, 3vw, 20px);
            }
            
            .back-button {
                font-size: clamp(0.85rem, 2vw, 1rem);
                padding: clamp(8px, 1.5vw, 12px) clamp(16px, 3vw, 20px);
            }
            
            .public-main {
                padding: clamp(10px, 2.5vw, 20px);
                width: 100%;
            }
            
            .event-detail-container {
                grid-template-columns: 1fr;
                gap: clamp(1.5rem, 4vh, 2.5rem);
                padding: 0;
            }
            
            .event-left-section,
            .event-right-section {
                width: 100%;
                min-width: 0;
            }
            
            .event-right-section {
                position: relative;
                top: 0;
            }
            
            .event-banner {
                height: clamp(200px, 30vh, 350px);
                border-radius: clamp(10px, 2vw, 16px);
                width: 100%;
            }
            
            .event-header {
                text-align: center;
                align-items: center;
                width: 100%;
            }
            
            .event-title {
                font-size: clamp(1.5rem, 6vw, 2.5rem);
                text-align: center;
                width: 100%;
            }
            
            .event-meta-row {
                flex-direction: column;
                justify-content: center;
                align-items: center;
                gap: clamp(0.75rem, 2vh, 1.25rem);
                width: 100%;
            }
            
            .event-stats {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: clamp(10px, 2vw, 14px);
                width: 100%;
            }
            
            .stat-box {
                padding: clamp(1rem, 2.5vw, 1.5rem);
                width: 100%;
                box-sizing: border-box;
            }
            
            .stat-value {
                font-size: clamp(1.5rem, 5vw, 2.25rem);
            }
            
            .stat-label {
                font-size: clamp(0.7rem, 1.8vw, 0.9rem);
            }
            
            .event-content-section {
                padding: clamp(1.25rem, 3vw, 2rem);
                width: 100%;
                box-sizing: border-box;
            }
            
            .section-title {
                font-size: clamp(1.15rem, 3vw, 1.5rem);
            }
            
            .section-content {
                font-size: clamp(0.85rem, 2vw, 1rem);
            }
            
            .action-buttons {
                flex-direction: column;
                gap: clamp(10px, 2vh, 14px);
                width: 100%;
            }
            
            .btn-large {
                width: 100%;
                padding: clamp(14px, 2vw, 18px) clamp(20px, 3vw, 28px);
                font-size: clamp(0.95rem, 2.2vw, 1.1rem);
                box-sizing: border-box;
            }
        }
        
        /* Large tablet and below */
        @media (max-width: 1200px) {
            .event-detail-container {
                grid-template-columns: 1fr;
                gap: clamp(1.5rem, 3vh, 2.5rem);
            }
            
            .event-right-section {
                position: relative;
                top: 0;
                width: 100%;
            }
            
            .event-stats {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: clamp(12px, 2vw, 16px);
            }
        }
        
        /* Tablet and below */
        @media (max-width: 1024px) {
            .event-detail-container {
                grid-template-columns: 1fr;
            }
            
            .event-right-section {
                position: relative;
                top: 0;
                width: 100%;
            }
            
            .event-stats {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: clamp(12px, 2vw, 16px);
            }
        }
        
        /* Tablet and below */
        @media (max-width: 768px) {
            .public-navbar {
                padding: 0 clamp(8px, 2.5vw, 15px);
            }
            
            .back-button {
                font-size: clamp(0.8rem, 2.5vw, 0.95rem);
                padding: clamp(8px, 2vw, 10px) clamp(14px, 3.5vw, 18px);
            }
            
            .public-main {
                padding: clamp(8px, 2vw, 20px);
                width: 100%;
            }
            
            .event-detail-container {
                gap: clamp(1rem, 3vh, 1.75rem);
                width: 100%;
                padding: 0;
            }
            
            .event-left-section,
            .event-right-section {
                width: 100%;
                min-width: 0;
            }
            
            .event-title {
                font-size: clamp(1.35rem, 5.5vw, 2rem);
            }
            
            .event-banner {
                height: clamp(160px, 25vh, 250px);
                width: 100%;
            }
            
            .event-meta-row {
                flex-direction: column;
                align-items: flex-start;
                gap: clamp(0.6rem, 1.5vh, 1rem);
                width: 100%;
            }
            
            .meta-item {
                font-size: clamp(0.8rem, 2vw, 0.95rem);
            }
            
            .event-stats {
                grid-template-columns: 1fr;
                gap: clamp(10px, 2vh, 14px);
                width: 100%;
            }
            
            .stat-box {
                padding: clamp(1rem, 3vw, 1.5rem);
                width: 100%;
                box-sizing: border-box;
            }
            
            .stat-value {
                font-size: clamp(1.75rem, 5vw, 2.25rem);
            }
            
            .stat-label {
                font-size: clamp(0.75rem, 2vw, 0.9rem);
            }
            
            .event-content-section {
                padding: clamp(1rem, 3.5vw, 1.75rem);
                width: 100%;
                box-sizing: border-box;
            }
            
            .section-title {
                font-size: clamp(1.1rem, 3.5vw, 1.4rem);
            }
            
            .section-content {
                font-size: clamp(0.85rem, 2.2vw, 1rem);
                line-height: 1.6;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: clamp(10px, 2vh, 12px);
                width: 100%;
            }
            
            .btn-large {
                width: 100%;
                justify-content: center;
                padding: clamp(12px, 2.5vw, 16px) clamp(18px, 3.5vw, 24px);
                font-size: clamp(0.9rem, 2.5vw, 1.05rem);
                box-sizing: border-box;
            }
        }
        
        /* Small mobile */
        @media (max-width: 480px) {
            .public-navbar {
                padding: 0 clamp(8px, 2.5vw, 12px);
            }
            
            .back-button {
                font-size: clamp(0.75rem, 3vw, 0.9rem);
                padding: clamp(6px, 2vw, 10px) clamp(12px, 3.5vw, 16px);
                gap: clamp(6px, 1.5vw, 8px);
            }
            
            .back-button svg {
                width: 16px;
                height: 16px;
            }
            
            .public-main {
                padding: clamp(8px, 2vw, 15px);
                width: 100%;
            }
            
            .event-detail-container {
                width: 100%;
                padding: 0;
            }
            
            .event-left-section,
            .event-right-section {
                width: 100%;
                min-width: 0;
            }
            
            .event-title {
                font-size: clamp(1.25rem, 6vw, 1.8rem);
                line-height: 1.3;
            }
            
            .event-banner {
                height: clamp(140px, 22vh, 200px);
                width: 100%;
            }
            
            .event-badge {
                font-size: clamp(0.75rem, 2.5vw, 0.9rem);
                padding: clamp(4px, 1vw, 6px) clamp(10px, 2.5vw, 16px);
            }
            
            .meta-item {
                font-size: clamp(0.75rem, 2.5vw, 0.85rem);
                gap: clamp(6px, 1.5vw, 8px);
            }
            
            .meta-item svg {
                width: clamp(14px, 3vw, 18px);
                height: clamp(14px, 3vw, 18px);
            }
            
            .event-content-section {
                padding: clamp(0.9rem, 4vw, 1.4rem);
                border-radius: clamp(10px, 2.5vw, 14px);
                width: 100%;
                box-sizing: border-box;
            }
            
            .section-title {
                font-size: clamp(1rem, 4vw, 1.3rem);
                margin-bottom: clamp(0.6rem, 1.5vh, 1rem);
            }
            
            .section-content {
                font-size: clamp(0.8rem, 2.5vw, 0.95rem);
                line-height: 1.5;
            }
            
            .stat-box {
                padding: clamp(0.9rem, 3.5vw, 1.3rem);
                width: 100%;
                box-sizing: border-box;
            }
            
            .stat-value {
                font-size: clamp(1.5rem, 6vw, 2rem);
                margin-bottom: clamp(0.4rem, 1vh, 0.6rem);
            }
            
            .stat-label {
                font-size: clamp(0.7rem, 2.5vw, 0.85rem);
            }
            
            .btn-large {
                padding: clamp(10px, 3vw, 14px) clamp(16px, 4vw, 20px);
                font-size: clamp(0.85rem, 3vw, 1rem);
                gap: clamp(6px, 1.5vw, 8px);
                width: 100%;
                box-sizing: border-box;
            }
            
            .btn-large svg {
                width: 18px;
                height: 18px;
            }
        }
        
        /* Extra small mobile */
        @media (max-width: 360px) {
            .event-stats {
                grid-template-columns: 1fr;
            }
            
            .event-meta-row {
                gap: clamp(0.5rem, 1.5vh, 0.75rem);
            }
            
            .btn-large {
                padding: clamp(10px, 2.5vw, 14px) clamp(16px, 4vw, 20px);
                font-size: clamp(0.85rem, 2.5vw, 0.95rem);
            }
        }
    </style>
</head>
<body>
    <!-- Public Navigation -->
    <nav class="public-navbar">
        <a href="<?php echo BASE_URL; ?>/index.php" class="back-button">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back to All Events
        </a>
    </nav>

    <!-- Main Content -->
    <main class="public-main">
        <div class="event-detail-container">
            <!-- Left Section: Banner & Details -->
            <div class="event-left-section">
                <!-- Event Banner -->
                <?php if (!empty($event['banner_image'])): ?>
                    <div class="event-banner" style="background-image: url('<?php echo BASE_URL; ?>/public/uploads/events/<?php echo htmlspecialchars($event['banner_image']); ?>')"></div>
                <?php else: ?>
                    <div class="event-banner" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)"></div>
                <?php endif; ?>

                <!-- Event Header -->
                <div class="event-header">
                    <?php if ($event['club_name']): ?>
                        <span class="event-badge"><?php echo htmlspecialchars($event['club_name']); ?></span>
                    <?php endif; ?>
                    
                    <h1 class="event-title"><?php echo htmlspecialchars($event['event_name']); ?></h1>
                    
                    <div class="event-meta-row">
                        <div class="meta-item">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <?php echo date('l, F d, Y', strtotime($event['event_date'])); ?>
                        </div>
                        <div class="meta-item">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <?php echo date('g:i A', strtotime($event['event_date'])); ?>
                        </div>
                        <div class="meta-item">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <?php echo htmlspecialchars($event['venue']); ?>
                        </div>
                    </div>
                </div>

                <!-- Event Description -->
                <?php if (!empty($event['description'])): ?>
                <div class="event-content-section">
                    <h2 class="section-title">About This Event</h2>
                    <div class="section-content">
                        <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Event Details -->
                <div class="event-content-section">
                    <h2 class="section-title">Event Details</h2>
                    <div class="section-content">
                        <p><strong>Event Type:</strong> <?php echo ucfirst(htmlspecialchars($event['event_type'])); ?></p>
                        <p><strong>Organized by:</strong> <?php echo htmlspecialchars($event['creator_name']); ?></p>
                        <?php if ($event['club_name']): ?>
                            <p><strong>Club:</strong> <?php echo htmlspecialchars($event['club_name']); ?></p>
                        <?php endif; ?>
                        <p><strong>Maximum Participants:</strong> <?php echo $event['max_participants']; ?></p>
                    </div>
                </div>
            </div>

            <!-- Right Section: Registration Info -->
            <div class="event-right-section">
                <!-- Event Stats -->
                <div class="event-stats">
                    <div class="stat-box">
                        <div class="stat-value">
                            <?php echo $event['max_participants'] - $event['current_participants']; ?>/<?php echo $event['max_participants']; ?>
                        </div>
                        <div class="stat-label">Spots Available</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value"><?php echo $event['current_participants']; ?></div>
                        <div class="stat-label">Registered</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value"><?php echo date('M d', strtotime($event['registration_deadline'])); ?></div>
                        <div class="stat-label">Registration Deadline</div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button class="btn-large btn-primary-large" onclick="registerForEvent()">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Register for This Event
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script src="<?php echo BASE_URL; ?>/public/js/toast.js"></script>
    <script>
        function registerForEvent() {
            const eventId = <?php echo $event_id; ?>;
            const eventName = <?php echo json_encode($event['event_name']); ?>;
            
            // Store the event they want to register for
            sessionStorage.setItem('pending_event_registration', eventId);
            sessionStorage.setItem('pending_event_name', eventName);
            
            // Redirect to login/register
            showToast('Please login or register to participate in: ' + eventName, 'info');
            
            setTimeout(() => {
                window.location.href = '<?php echo BASE_URL; ?>/login.php?redirect=event&event_id=' + eventId;
            }, 1500);
        }
    </script>
</body>
</html>
