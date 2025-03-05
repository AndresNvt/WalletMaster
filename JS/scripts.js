// Función para manejar la animación de login y registro
function initLoginRegisterAnimation() {
	const container = document.querySelector('.logsig-container');
	const LoginLink = document.querySelector('.SignInLink');
	const RegisterLink = document.querySelector('.SignUpLink');
  
	RegisterLink.addEventListener('click', () => {
	  container.classList.add('active');
	});
  
	LoginLink.addEventListener('click', () => {
	  container.classList.remove('active');
	});
  }
  

window.addEventListener("DOMContentLoaded", () => {
	const blockContainer = document.getElementById('blocks');
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
});
		




  document.addEventListener('DOMContentLoaded', () => {
	initLoginRegisterAnimation();
  });
  