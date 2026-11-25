-- 25/11/2026 (passo 85, pedido do usuário): o assistente do WhatsApp passou a se chamar LocalizaBot (era Amarilis).
RENAME TABLE amarilis_conversas TO localizabot_conversas, amarilis_mensagens TO localizabot_mensagens, amarilis_recados TO localizabot_recados;
-- coluna de: 'pessoa' ou 'bot' (25/11; antes 'amarilis')
