-- 22/11 (passo 40): toque no botão "E-mail" da ficha conta como contato
ALTER TABLE contatos MODIFY tipo ENUM('perfil','whatsapp','ligar','instagram','mapa','site','compartilhar','email') NOT NULL;
