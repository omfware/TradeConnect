<?php
// utils.php
// Ruta al avatar por defecto (SVG que descargaste)
define('AVATAR_DEFAULT', 'assets/avatar-default.svg');

/**
 * Devuelve una URL de avatar válida.
 * - Si $url está vacía o el archivo no existe en el servidor, devuelve el avatar por defecto.
 * - Si $url existe, devuelve la misma $url.
 */
function avatar_url(?string $url): string {
  $u = trim((string)$url);
  if ($u === '') return AVATAR_DEFAULT;

  // Chequeo de existencia local (evita 404 si alguien borró la imagen)
  $localPath = __DIR__ . '/' . ltrim($u, '/');
  return (is_file($localPath)) ? $u : AVATAR_DEFAULT;
}

/**
 * Clase de seguridad para manejo de contraseñas usando CONSTRUCTOR (requisito).
 * - new Seguridad($passwordPlano) genera el hash.
 * - getHash(): devuelve el hash.
 * - verificar($pwd, $hash): compara una contraseña con el hash.
 */
class Seguridad {
  private string $passwordPlano;
  private string $hashGenerado;

  public function __construct(string $passwordPlano) {
    $this->passwordPlano = $passwordPlano;
    $this->hashGenerado = password_hash($passwordPlano, PASSWORD_BCRYPT);
  }

  public function getHash(): string {
    return $this->hashGenerado;
  }

  public static function verificar(string $passwordIngresada, string $hashGuardado): bool {
    return password_verify($passwordIngresada, $hashGuardado);
  }
}
