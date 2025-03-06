// Función para manejar la animación de login y registro
function initLoginRegisterAnimation() {
	const container = document.querySelector('.logsig-container');
	if (container) {
	  const LoginLink = document.querySelector('.SignInLink');
	  const RegisterLink = document.querySelector('.SignUpLink');
	  
	  if (RegisterLink) {
		RegisterLink.addEventListener('click', () => {
		  container.classList.add('active');
		});
	  }
	  
	  if (LoginLink) {
		LoginLink.addEventListener('click', () => {
		  container.classList.remove('active');
		});
	  }
	}
  }
  
  // Función para manejar los bloques interactivos en el header
  function initBlocksAnimation() {
	const blockContainer = document.getElementById('blocks');
	if (!blockContainer) return;
	
	const blockSize = 50;
	const screenWidth = window.innerWidth;
	const screenHeight = window.innerHeight;
	const numCols = Math.ceil(screenWidth / blockSize);
	const numRows = Math.ceil(screenHeight / blockSize);
	const numBlocks = numCols * numRows;
  
	function createBlocks() {
	  for (let i = 0; i < numBlocks; i++) {
		const block = document.createElement("div");
		block.classList.add("block");
		block.dataset.index = i;
		blockContainer.appendChild(block);
	  }
	}
  
	function highlightBlock(x, y) {
	  const rect = blockContainer.getBoundingClientRect();
	  const relativeX = x - rect.left;
	  const relativeY = y - rect.top;
  
	  const col = Math.floor(relativeX / blockSize);
	  const row = Math.floor(relativeY / blockSize);
	  const index = row * numCols + col;
  
	  if (col >= 0 && col < numCols && row >= 0 && row < numRows && index >= 0 && index < numBlocks) {
		const block = blockContainer.children[index];
		if (block) {
		  block.classList.add('highlight');
		  setTimeout(() => {
			block.classList.remove('highlight');
		  }, 500);
		  highlightNeighbors(index);
		}
	  }
	}
  
	function highlightNeighbors(index) {
	  const neighbors = [
		index - 1, index + 1,
		index - numCols, index + numCols,
		index - numCols - 1, index - numCols + 1,
		index + numCols - 1, index + numCols + 1
	  ].filter(
		(i) =>
		  i >= 0 &&
		  i < numBlocks &&
		  Math.abs((i % numCols) - (index % numCols)) <= 1
	  );
  
	  neighbors.forEach((nIndex) => {
		const neighbor = blockContainer.children[nIndex];
		if (neighbor) {
		  neighbor.classList.add('highlight');
		  setTimeout(() => {
			neighbor.classList.remove('highlight');
		  }, 500);
		}
	  });
	}
  
	document.addEventListener('mousemove', (event) => {
	  const x = event.clientX;
	  const y = event.clientY;
	  highlightBlock(x, y);
	});
  
	createBlocks();
  }
  
  // Función mejorada para manejar el comportamiento de la navbar
  function initNavbarBehavior() {
	const navbar = document.querySelector('.navbar');
	if (!navbar) return;
	
	// Aplicar configuraciones iniciales
	navbar.style.position = 'fixed';
	navbar.style.top = '0';
	navbar.style.width = '100%';
	navbar.style.zIndex = '1030';
	
	// Configurar margen inicial basado en el tamaño de pantalla
	if (window.innerWidth >= 992) {
	  navbar.style.margin = '20px 20px 0 20px';
	  navbar.style.borderRadius = '10px';
	} else {
	  navbar.style.margin = '0';
	  navbar.style.borderRadius = '0';
	}
	
	// Función para manejar el scroll
	function handleScroll() {
	  if (window.scrollY > 50) {
		navbar.classList.add('scrolled');
		navbar.style.margin = '0';
		navbar.style.borderRadius = '0';
	  } else {
		navbar.classList.remove('scrolled');
		// Restaurar margen solo en pantallas grandes
		if (window.innerWidth >= 992) {
		  navbar.style.margin = '20px 20px 0 20px';
		  navbar.style.borderRadius = '10px';
		}
	  }
	  
	  // Actualizar enlaces de navegación activos según la posición del scroll
	  updateActiveNavLinks();
	}
	
	// Actualizar enlaces de navegación activos basados en la posición actual del scroll
	function updateActiveNavLinks() {
	  const scrollPosition = window.scrollY + 200; // Offset para mejor UX
	  const sections = document.querySelectorAll('section, header, .cards-1, .tabs, .screen-1');
	  const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
	  
	  // Encontrar la sección actual en la vista
	  let currentSection = '';
	  sections.forEach(section => {
		if (!section.id) return;
		const sectionTop = section.offsetTop;
		const sectionHeight = section.offsetHeight;
		
		if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
		  currentSection = section.id;
		}
	  });
	  
	  // Actualizar enlaces de navegación
	  navLinks.forEach(link => {
		link.classList.remove('active');
		const href = link.getAttribute('href');
		if (href && href.includes(currentSection)) {
		  link.classList.add('active');
		}
	  });
	}
	
	// Manejar colapso del menú en móvil
	const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
	const navbarToggler = document.querySelector('.navbar-toggler');
	const navbarCollapse = document.getElementById('navbarCollapse');
	
	if (navbarToggler && navbarCollapse && typeof bootstrap !== 'undefined') {
	  navLinks.forEach(link => {
		link.addEventListener('click', () => {
		  if (window.innerWidth < 992 && navbarCollapse.classList.contains('show')) {
			const bsCollapse = new bootstrap.Collapse(navbarCollapse);
			bsCollapse.hide();
		  }
		});
	  });
	}
	
	// Registrar event listeners
	window.addEventListener('scroll', handleScroll);
	window.addEventListener('resize', handleScroll);
	
	// Ejecutar una vez al inicio
	handleScroll();
  }
  
  // Asegurar que el script se ejecute cuando el DOM esté completamente cargado
  document.addEventListener('DOMContentLoaded', () => {
	// Inicializar la animación de login/registro
	initLoginRegisterAnimation();
	
	// Inicializar la animación de bloques
	initBlocksAnimation();
	
	// Inicializar el comportamiento de la navbar
	initNavbarBehavior();
  });
  
  // También ejecutar cuando la ventana esté completamente cargada
  window.addEventListener('load', () => {
	// Reinicializar el comportamiento de la navbar para garantizar que funcione
	initNavbarBehavior();
  });