

const container=document.querySelector('.logsig-container');
const LoginLink=document.querySelector('.SignInLink');
const RegisterLink=document.querySelector('.SignUpLink');
RegisterLink.addEventListener('click',()=>{
	container.classList.add('active');
})
LoginLink.addEventListener('click',()=>{
	container.classList.remove('active');
})


