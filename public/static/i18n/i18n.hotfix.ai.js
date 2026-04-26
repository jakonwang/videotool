(function () {
  if (!window.AppI18n || !window.AppI18n._dict) return;
  var dict = window.AppI18n._dict;
  var zh = dict.zh || (dict.zh = {});
  var en = dict.en || (dict.en = {});
  var vi = dict.vi || (dict.vi = {});

  var zhPatch = {
    'admin.menu.aiCommander': '\u667a\u80fd\u4e2d\u67a2',
    'page.aiCenter.title': 'AI\u7ecf\u8425\u6307\u6325\u5b98',
    'page.aiCenter.breadcrumb': '\u589e\u957f\u5206\u6790 / AI\u7ecf\u8425\u6307\u6325\u5b98',
    'page.aiCenter.heroTitle': '\u4f1a\u8bca\u65ad\u3001\u4f1a\u7b56\u5212\u3001\u4f1a\u63a8\u8fdb\u7684\u7ecf\u8425\u4e2d\u67a2',
    'page.aiCenter.heroSummary': '\u76ee\u6807\u9a71\u52a8 + \u6570\u636e\u8bc1\u636e + \u4eba\u673a\u534f\u540c\u5ba1\u6279',
    'page.aiCenter.chatSub': '\u804a\u5929\u6307\u6325\u53f0 + \u81ea\u52a8\u5de5\u4f5c\u6d41\uff08\u4e00\u671f\uff09',
    'page.aiCenter.planSub': '\u7ed3\u6784\u5316\u884c\u52a8\u8ba1\u5212 + \u5ba1\u6279\u6267\u884c\u72b6\u6001',
    'page.aiCenter.commandDiagnose': '\u8bca\u65ad',
    'page.aiCenter.commandPlan': '\u7b56\u5212',
    'page.aiCenter.commandPush': '\u63a8\u8fdb',
    'page.aiCenter.chatPlaceholder': '\u8f93\u5165\u6307\u4ee4\uff0c\u4f8b\u5982\uff1a\u4e3a\u4ec0\u4e48\u4eca\u5929\u6389\u91cf\uff1f\u8bf7\u751f\u621014\u5929\u653e\u91cf\u8ba1\u5212\u3002',
    'page.aiCenter.goalGmv': '\u76ee\u6807GMV\uff08\u53ef\u9009\uff09',
    'page.aiCenter.roiFloor': 'ROI\u4e0b\u9650\uff08\u53ef\u9009\uff09',
    'page.aiCenter.dailyInsight': '\u4eca\u65e5\u6d1e\u5bdf',
    'page.aiCenter.send': '\u53d1\u9001\u6307\u6325',
    'page.aiCenter.generatePlan': '\u751f\u6210\u8ba1\u5212',
    'page.aiCenter.executePlan': '\u6267\u884c\u8ba1\u5212',
    'page.aiCenter.todayDo': '\u4eca\u65e5\u8981\u505a',
    'page.aiCenter.todayAvoid': '\u4eca\u65e5\u4e0d\u8981\u505a',
    'page.aiCenter.evidence': '\u8bc1\u636e\u94fe',
    'page.aiCenter.planCenter': '\u8ba1\u5212\u4e2d\u5fc3',
    'page.aiCenter.stage': '\u8d26\u6237\u9636\u6bb5',
    'page.aiCenter.mainProblem': '\u4e3b\u95ee\u9898',
    'page.aiCenter.actions': '\u4e0b\u4e00\u6b65\u52a8\u4f5c',
    'page.aiCenter.confidence': '\u7f6e\u4fe1\u5ea6',
    'page.aiCenter.risk': '\u98ce\u9669\u7b49\u7ea7',
    'page.aiCenter.approval': '\u5ba1\u6279',
    'page.aiCenter.noData': '\u6682\u65e0\u6570\u636e\uff0c\u8bf7\u5148\u53d1\u9001\u6307\u6325\u6216\u751f\u6210\u8ba1\u5212',
    'page.aiCenter.colId': 'ID',
    'page.aiCenter.colPlan': '\u8ba1\u5212',
    'page.aiCenter.colTasks': '\u4efb\u52a1\u6570',
    'page.aiCenter.colRisk': '\u98ce\u9669',
    'page.aiCenter.colStatus': '\u72b6\u6001',
    'page.aiCenter.colDue': '\u622a\u6b62\u65f6\u95f4',
    'page.aiCenter.colAction': '\u64cd\u4f5c',
    'page.aiCenter.statusDraft': '\u8349\u7a3f',
    'page.aiCenter.statusRunning': '\u6267\u884c\u4e2d',
    'page.aiCenter.statusCompleted': '\u5df2\u5b8c\u6210',
    'page.aiCenter.statusPaused': '\u5df2\u6682\u505c',
    'page.aiCenter.statusCancelled': '\u5df2\u53d6\u6d88',
    'page.aiCenter.riskLow': '\u4f4e',
    'page.aiCenter.riskMedium': '\u4e2d',
    'page.aiCenter.riskHigh': '\u9ad8',
    'page.aiCenter.riskCritical': '\u6781\u9ad8',
    'page.aiCenter.stageColdStart': '\u51b7\u542f\u52a8',
    'page.aiCenter.stageLearning': '\u5b66\u4e60\u671f',
    'page.aiCenter.stageScaling': '\u653e\u91cf\u671f',
    'page.aiCenter.stageStable': '\u7a33\u5b9a\u671f',
    'page.aiCenter.stageDecay': '\u8870\u9000\u671f',
    'page.aiCenter.problemFront3s': '\u524d3\u79d2\u95ee\u9898',
    'page.aiCenter.problemMiddle': '\u4e2d\u6bb5\u95ee\u9898',
    'page.aiCenter.problemConversionTail': '\u8f6c\u5316\u6bb5\u95ee\u9898',
    'page.aiCenter.problemMultiStage': '\u591a\u9636\u6bb5\u95ee\u9898',
    'page.gmvMax.filterStore': '\u9009\u62e9\u5e97\u94fa',
    'page.gmvMax.filterCampaign': 'Campaign ID\uff08\u53ef\u9009\uff09'
  };

  var enPatch = {
    'admin.menu.aiCommander': 'AI Center',
    'page.aiCenter.chatSub': 'Chat command desk + workflow (Phase 1)',
    'page.aiCenter.planSub': 'Structured action plans + approval execution status'
  };

  var viPatch = {
    'admin.menu.aiCommander': 'Trung tam AI',
    'page.aiCenter.chatSub': 'Bang dieu khien chat + workflow (Giai doan 1)',
    'page.aiCenter.planSub': 'Ke hoach hanh dong cau truc + trang thai phe duyet'
  };

  Object.assign(zh, zhPatch);
  Object.assign(en, enPatch);
  Object.assign(vi, viPatch);
})();

