// ---------------------------
// 1) Efecto de escritura
// ---------------------------
document.addEventListener('DOMContentLoaded', () => {  // Escucha el evento DOMContentLoaded. Espera a que el HTML esté totalmente cargado antes de correr este código
  const text = "Encuentra Profesionales Locales para tus Necesidades"; // Declara una constante llamada text. Guarda el texto completo que se va a “escribir letra por letra”.
  const title = document.getElementById('typing-title'); // Busca en el HTML el elemento con id="typing-title". Lo guarda en la variable title.
  let index = 0; // Declara una variable index. Va a controlar en qué letra vamos mientras escribimos.

  function type() { // Declara una funcion llamada 'type' .Es la que va a ir agregando letra por letra al título.
    if (index < text.length) { // Comprueba si index todavía no llegó al final del texto. text.length = número total de caracteres.
      title.textContent += text.charAt(index); // Usa text.charAt(index) para obtener la letra en la posición actual. La agrega al contenido del título (+= va sumando letras).
      index++; // Suma 1 a index. Avanza a la siguiente letra.
      setTimeout(type, 50); // Espera 50 milisegundos y vuelve a llamar la función type(). Crea el efecto de máquina de escribir.
    }
  }

  type(); //lama una vez a type() para empezar el proceso.
});

