import { callService } from "@/common/common.js";

export function login(code) {
  let params = {
    code: code,
  };

  return callService("motong.oauth.wechatMiniappLogin", params);
}
