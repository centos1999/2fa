# WHMCS Secure Login 插件（WHMCS 7）

本插件在高风险登录场景下要求客户进行邮箱验证码验证，提供风控签到、重发冷却与次数限制、设备记住、支付宽限、异地/设备风险校验等能力，适用于提升客户中心登录安全与合规。

## 主要功能

- 登录风险触发邮箱验证码验证
  - 首次登录（可配置）
  - 超过 N 天未进行已验证登录
  - 异地/设备变化（可配置，见下文“启用异地/设备检测”）
  - 管理员强制下次登录验证
- 验证码发送与重发
  - 登录时自动发送：首次登录/注册一定自动发送；其后若重发冷却结束并且今日未达上限，会在登录时自动发送；否则展示倒计时或上限提示
  - 重发冷却：默认 60 秒（可配置）
  - 每日发送上限：默认 5 次（可配置）；达到上限后按钮禁用并显示“今日验证码发送次数已达上限”
  - 严格检测发送结果：发送失败会在日志体现，界面不显示成功
- 验证码有效期与错误锁定
  - 验证码有效期默认 5 分钟（可配置）
  - 错误次数上限默认 5 次（可配置）；达到后按“小时”为单位锁定（配置项“错误锁定时间(小时)”），前端倒计时以“剩余N小时 N分钟 N秒”显示
- 记住设备
  - 勾选“记住此设备”后，写入安全 Cookie（Secure/HttpOnly/SameSite=Lax），后续登录在同一设备与指纹下可免验证
- 支付宽限
  - 在未完成验证前允许访问发票/结账等白名单页面，避免支付流程被打断
- 管理后台
  - 查看日志/用户状态、手动解锁、强制验证、清除“记住设备”、按分钟/小时维度锁定（内部以分钟执行）
  - 管理端表单带 CSRF 校验与二次确认

## 安装与升级

1. 将 securelogin 目录放置于 WHMCS modules/addons/ 下（根据环境调整）
2. 后台-系统设置-Addon Modules 启用“安全登录 (Secure Login)”并保存配置
3. 激活时会自动创建必要数据表与三份邮件模板：
   - Secure Login Verification Code
   - Secure Login Registration Code
   - Secure Login Email Change Code（用于邮箱变更场景，当前逻辑为“变更后下次登录验证”）

## 配置说明（WHMCS 后台 Addon 设置）

- 启用：是否启用插件
- 超过多少天未登录需要验证（days_threshold）：默认 15 天
- 启用异地/设备检测（enable_geo_check）：勾选后启用风控检查
- 启用首次登录验证（enable_first_login）：默认启用
- 验证码有效期(分钟)（code_expiry_minutes）：默认 5
- 验证码重发间隔(秒)（resend_interval_seconds）：默认 60
- 最大重发次数（max_resend）：默认 5（按“日”计算，基于 WHMCS 系统时区的自然日）
- 最大错误次数（max_attempts）：默认 5
- 错误锁定时间(小时)（lock_minutes）：默认 1（注意：该项单位已改为“小时”，内部执行时换算为分钟）
- 记住设备天数（remember_device_days）：默认 30 天

## 启用异地/设备检测的检查逻辑（重要）

当“启用异地/设备检测”勾选时，登录后将根据下列规则判断是否需要验证：

- 计算设备指纹：基于 User-Agent、Accept-Language、Sec-CH-UA/Platform/Mobile、Accept、DNT 等信息生成哈希
- 检测条件：仅当“IP 变化 且 设备指纹变化”同时满足才触发验证，降低误报
- 本插件不再进行城市解析与任何外部地理 API 调用，也不再注入异步上报脚本

注意：插件仅从可信代理头部中获取客户端 IP（若存在反代/CF，请确保后端仅信任受控来源的头部）。

## 行为与交互细节

- 首次登录/注册：自动发送验证码
- 冷却期内（例如 60 秒）：不会自动重发，按钮显示“还剩N秒”并禁用
- 冷却结束后再次登录：若未达本日上限，将自动发送验证码；否则按钮显示“今日验证码发送次数已达上限”并禁用
- 在同一会话内刷新页面：倒计时与上限显示保持一致（基于 last_sent_at 计算）
- 错误超过上限：账户进入锁定，前端展示“剩余N小时 N分钟 N秒”倒计时

## 安全与合规

- 前后台所有表单均加入 CSRF 防护
- 记住设备 Cookie：Secure/HttpOnly/SameSite=Lax；并校验设备指纹（兼容旧指纹）
- 不进行任何外部地理位置请求
- 邮件发送严格检查 sendMessage 调用异常并记录日志

## 日志

所有关键事件均记录在 mod_securelogin_logs 表：
- code_issued / code_resent / login_throttled_no_send / login_daily_limit_no_send / verify_success / verify_failed / skip_issue_locked / skip_resend_locked 等

## 兼容性

- 适配 WHMCS 7.x（依赖 WHMCS 内置 sendMessage 与 Capsule）
- 多语言界面：前端验证页内置中英文 UI 文案

## 变更提示

- 配置“错误锁定时间(分钟)”已经更新为“错误锁定时间(小时)”，请根据需要调整数值
- 重发次数改为“按日计数”，在 WHMCS 系统时区的自然日 0 点重置

## 维护

- 如需禁用异地/设备风险校验，可在 Addon 设置中取消勾选“启用异地/设备检测”
- 建议正确配置发信域的 SPF/DKIM/DMARC，避免进入垃圾箱或被服务商限流

## 生产就绪检查与建议（Audit）

结论：插件已具备在生产环境使用的基本条件。为确保稳定上线，建议在发布前核对以下清单并按需调优：

- 配置与环境
  - 核对 WHMCS 系统时区设置，确保“验证码发送次数上限”在本地时区每日 0 点自动重置。
  - 配置可靠的 SMTP/邮件网关，并评估发信速率与供应商限流策略。
  - 全站启用 HTTPS；如使用反向代理/Cloudflare，确保仅信任受控来源的真实 IP 头部（如 CF-Connecting-IP）。
- 安全
  - 本插件已对前后台表单加入 CSRF 校验；“记住设备”Cookie 使用 Secure/HttpOnly/SameSite=Lax。
  - 建议：可选在“重发验证码”动作前加入基础人机校验（如 reCAPTCHA）以进一步降低邮箱枚举/滥用风险（未默认启用）。
  - 建议：在现有“按用户冷却 + 每日上限”基础上，考虑叠加“按IP/用户+IP”的速率限制或黑名单以应对异常流量（未默认启用）。
- 稳定性与可观测性
  - 确认三份邮件模板存在且未被禁用：Secure Login Verification Code / Registration Code / Email Change Code。
  - 建议：为 mod_securelogin_logs 设置日志保留/归档策略，避免长期增长影响库体积。
- 隐私与合规
  - 仅在启用“异地/设备检测”时进行城市解析，且只使用 HTTPS 提供商（ipapi.co）。如有更严格合规要求，可关闭该功能或替换为内网/自建地理库。
- 主题与兼容性
  - 验证页面卡片会自动插入到页面标题下方，并整体上移 50px；如有深度定制主题，可按需微调容器的 margin-top。

本节列出的建议未直接修改代码，仅供部署前评估与加固使用。

## 已改动摘要

- 验证界面卡片整体上移 50px（verify.tpl：容器 margin-top 设为 -50px）。
- 将“验证码发送次数上限”的重置逻辑从 UTC 自然日调整为“WHMCS 系统时区的自然日 0 点”自动重置（helpers.php）。
- 管理端“取消强制验证”现采用“一次性豁免”策略：仅在用户下一次真实登录时放行，并在登录成功后恢复正常安全策略，避免把管理员当前 IP/指纹写入用户状态。
- 后台最近全局日志时间显示已按 WHMCS 系统时区展示（数据库仍存 UTC），避免跨区域时区混淆（securelogin.php）。
- 验证通过、记住设备放行或一次性豁免放行后，会话将执行 session_regenerate_id(true) 并写入 WHMCS 全局日志 logActivity，以降低会话固定风险、补齐审计闭环（hooks.php / securelogin.php）。
- 移除城市解析与异步上报：不再调用任何外部地理位置服务，不再注入 async_city_update 脚本（helpers.php / hooks.php / securelogin.php）。

## 生产规模评估（10万+ 注册用户）

结论：现有实现可用于生产，建议按下列要点进行容量与可靠性加固。

- 数据库表与索引
  - mod_securelogin_logs：建议新增索引 (userid, id) 或 (userid, created_at)。当前仅按 id 降序可快速取最近 100 条全局日志，但“按用户查看最近日志”会走 where userid + order by id，如无 userid 索引在数据量大（千万级）时会变慢。
  - mod_securelogin_state：已对 userid 建普通索引，但无唯一约束/主键。并发首次登录极端情况下可能产生重复行；建议 UNIQUE(userid) 或以 userid 为主键。
  - mod_securelogin_codes：同上，建议 UNIQUE(userid) 或增加自增主键并确保 (userid) 唯一。否则在并发创建时可能出现重复记录，虽然功能不受影响但浪费存储并增加维护复杂度。
  - mod_securelogin_devices：已存在 token_hash、userid 索引，满足查找。可选优化：为 (userid, token_hash) 增加唯一索引以避免同一设备重复写入。
  - mod_securelogin_email_changes：已有 (userid, status) 索引。可选补充 created_at 索引用于清理/报表。

- 清理与归档
  - 日志清理当前按 created_at < cutoff 一次性删除，量大时可能触发长事务/锁。建议：
    - 为 created_at 建索引，加速范围删除；
    - 采用分批清理（例如每批 5000 条循环删除），减少表锁与回滚段压力；
    - 大规模场景可考虑日志落地到独立库/分区表或外部日志系统（ELK/ClickHouse），WHMCS 中仅保留近 7~14 天必要审计。

- 发送验证码（发信链路）
  - 当前在登录 Hook 内直接 sendMessage，同步 SMTP/网关延迟会拉长跳转到验证页的时间。高峰期建议：
    - 引入队列/异步发信（失败回退为同步）；
    - 或策略调整：仅首次登录/注册自动发送，其余场景引导用户点击“发送验证码”。
  - 已有冷却与每日上限，建议叠加 IP/用户+IP 维度速率限制，降低恶意刷码风险。

- 登录性能
  - 城市解析已移除，不再进行任何异步上报或外部地理请求；风险判定仅依赖 IP 与设备指纹。
  - 状态/验证码查询均为按 userid 的单行操作，且表上存在 userid 索引，常规延迟低。
  - 主要开销在日志写入与发信调用。建议 log_retention_days 取较小值（7~14 天）并确保数据库 IO 能力。

- 数据一致性与并发
  - state/codes 表在并发首次访问时可能竞争“首次插入”，无唯一约束会造成重复行。当前代码 where userid 更新会同时更新多行，功能上可用但不优雅；建议加唯一约束，并在插入时采用“插入或忽略/更新”语义。
  - 重发计数/冷却无显式事务，极端并发下可能偶发 off-by-one；属可接受范围。

- 安全性补充
  - 关键放行点已执行 session_regenerate_id(true)，并写入 logActivity；
  - 记住设备 Cookie 使用 Secure/HttpOnly/SameSite=Lax，且校验设备指纹（含 UA/AL/Sec-CH）；
  - 依赖 X-Forwarded-For/CF-Connecting-IP 获取真实 IP，务必在网关层控制信任来源，防止伪造。

- 兼容性与时区
  - 日志展示使用 PHP 默认时区。若服务器 PHP 时区与 WHMCS 系统时区不一致，可能仍产生偏差。建议在 PHP ini/入口处统一时区，或在插件中从 WHMCS 配置读取系统时区。

- 运维与监控建议
  - 监控 SMTP 发信成功率/延迟；
  - 监控下列表行数与增长率：mod_securelogin_logs、mod_securelogin_devices（可定期清理过期设备）；
  - 开启数据库慢查询日志，观察“按用户查看最近日志”是否触发全表扫描，必要时补充索引。

以上为检查结论与实现摘要，已同步更新代码与文档以反映最新逻辑。

## 更新日志

- 新增列 last_sent_local_date（DATE），用于“每日发送次数”的原子化计数：在单条 SQL 中以 IF(last_sent_local_date=今日, resend_count+1, 1) 同时更新 resend_count、last_sent_local_date 与 last_sent_at（UTC），不依赖数据库时区表，避免并发下读写竞争与丢计数。
- 调整“每日上限 vs 冷却”的互斥逻辑：每日上限优先于冷却判断，且当达上限时服务端强制将 countdown 清零（0），前端不再显示倒计时，避免“上限但仍显示倒计时”的误导。
- securelogin_canResend() 改为基于 last_sent_local_date 判断“今日”计数，减少跨时区/夏令时转换导致的边界复杂度。
- 插件激活/升级时自动迁移新增列 last_sent_local_date（securelogin_activate）：对已存在的表执行 ALTER TABLE 以兼容历史环境。
- 语言优化：前端语言优先使用 WHMCS 当前语言，未知语言统一回退英文；保留页面手动切换按钮功能。
- 服务器端消息双语化：securelogin_clientarea 内错误/提示消息按 WHMCS 语言输出（zh/en），解决“界面英文但错误中文”的不一致。
- 语言会话策略：登录后默认跟随 WHMCS 语言；用户在验证页手动切换语言后，本次登录会话内优先使用用户选择的语言，不再自动切换；下次重新登录自动回到跟随 WHMCS 语言。
