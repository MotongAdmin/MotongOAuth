import { login } from './user.js'

export function wxCodeLogin(complete) {
	uni.login({
		provider: 'weixin',
		success: function(res) {
			console.log('wxlogin:' + JSON.stringify(res))
			let errMsg = res.errMsg
			let code = res.code
			if (errMsg == 'login:ok' && code !== null && code !== '') {
				login(code).then(loginRes => {
					console.log(loginRes)
					if(typeof complete == 'function') {
						complete()
					}
				}).catch(error => {
					if(typeof complete == 'function') {
						complete()
					}
				})
			} else {
				if(typeof complete == 'function') {
					complete()
				}
			}
		},
		fail(error) {
			if(typeof complete == 'function') {
				complete()
			}
			console.log('uni login fail:' + error)
		}
	})
}